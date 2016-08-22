<?php


namespace Divergentsoft;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class Push
 * @package Push
 */
class Push
{
    /**
     * Apple's production endpoint
     */
    const PRODUCTION_SERVER = 'https://api.push.apple.com';

    /**
     * Apple's sandbox endpoint
     */
    const SANDBOX_SERVER = 'https://api.development.push.apple.com';

    /**
     * The number of concurrent connections to create with the APNS endpoint
     */
    const CONCURRENT_CONNECTOIONS = 150;

    protected static $INVALID_TOKEN_RESPONSES = [
        "BadDeviceToken",
        "DeviceTokenNotForTopic",
        "Unregistered"
    ];

    /**
     * @var Monolog logger instance
     */
    protected $log;

    /**
     * @var
     */
    protected $client;

    /**
     * @var
     */
    protected $server;

    /**
     * @var
     */
    protected $production;

    /**
     * @var
     */
    protected $certificate;

    /**
     * @var
     */
    protected $passphrase;

    /**
     * @var
     */
    protected $failedTokens;


    /**
     * Push constructor. Pass in the log location.
     * @param $logLocation
     */
    public function __construct($logLocation = null)
    {
        $this->log = new Logger('Push');

        if ($logLocation == null) {

            $logLocation = __DIR__ . "/../log.txt";
        }

        if (!is_file($logLocation)) {

            fopen($logLocation, 'a');
        }

        if ($logLocation != null) {

            $this->log->pushHandler(new StreamHandler($logLocation), Logger::WARNING);
        }
    }
    /**
     * Tries to connect to desired APNS and returns false if unavailable
     *
     * @param $production
     * @param $certificate
     * @param null $passphrase
     * @return bool
     */
    public function connect($production, $certificate, $passphrase = null)
    {
        $this->production = $production;

        $this->certificate = $certificate;

        $this->passphrase = $passphrase;

        try {
            $this->verifyEnvironment($production, $certificate);

        } catch(PushException $e) {

            $this->log->addNotice("Failed to verify push notification environment. Invalid APNS certificate location");

            return false;
        }
        
        return true;
    }

    /**
     * Send the push notification(s)
     *
     * @param Message $message
     */
    public function send(Message $message, $apns_topic = null)
    {
        $this->log->addNotice("*** Initiating push delivery ***");

        $http2ch_group = array();

        $mh = curl_multi_init();

        for ($i=0; $i < static::CONCURRENT_CONNECTOIONS  ; $i++) { // Chunk size

            $http2ch = curl_init();

            $this->setCurlProperties($http2ch, $message, $apns_topic);

            curl_multi_add_handle($mh, $http2ch);

            $http2ch_group[] = $http2ch;
        }

        $token_group = array_chunk($message->recipients, static::CONCURRENT_CONNECTOIONS);

        foreach ($token_group as $key => $tokens) {

            $this->sendHTTP2PushToGroup($http2ch_group, $this->server, $tokens, $mh);
        }
        
        foreach ($http2ch_group as $http2ch) {
            
            curl_multi_remove_handle($mh, $http2ch);

            curl_close($http2ch);
        }

        curl_multi_close($mh);

        $this->log->addNotice("*** Completed push delivery ***");
    }

    protected function sendHTTP2PushToGroup($http2ch_group, $http2_server, $tokens, $mh) {

        $this->configureMultiExec($tokens, $http2_server, $http2ch_group, $mh);

        $this->runMultiExec($mh);

        $this->recordResultsAndReset($tokens, $http2ch_group, $mh);
    }

    /**
     * If any tokens are rejected by APNS they end up here
     *
     * @return array All of the failed tokens or null
     */
    public function getFailedTokens()
    {
        return $this->failedTokens;
    }

    protected function setCurlProperties($http2ch, $message, $apns_topic) {

        if (!defined('CURL_HTTP_VERSION_2_0')) {
            define('CURL_HTTP_VERSION_2_0', 3);
        }

        $headers = array(
            "apns-topic: {$apns_topic}"
        );

        curl_setopt_array($http2ch, [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_PORT => 443,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => $message->encodedMessage,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSLCERT => $this->certificate,
            CURLOPT_HEADER => 1
        ]);
    }

    protected function runMultiExec($mh) {

        $running = null;

        do {

            curl_multi_exec($mh, $running);
        }

        while ($running > 0);
    }

    protected function configureMultiExec($tokens, $http2_server, $http2ch_group, $mh) {

        foreach ($tokens as $key => $token) {

            $url = "{$http2_server}/3/device/{$token}";

            curl_setopt($http2ch_group[$key], CURLOPT_URL, $url);

            curl_multi_add_handle($mh, $http2ch_group[$key]);
        }
    }
    
    protected function recordResultsAndReset($tokens, $http2ch_group, $mh) {

        foreach ($tokens as $key => $token) {

            $this->addToFailedTokensIfFailed($http2ch_group[$key], $token);

            $this->logSend($http2ch_group[$key], $token);

            curl_multi_remove_handle($mh, $http2ch_group[$key]);
        }
    }

    /**
    * @param $status
    * @param $result
    * @param $token
    */
    protected function logSend($http2ch, $token) {

        $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);

        $result = curl_multi_getcontent($http2ch);

        if ($status == 200) {

             $this->log->addNotice("Message delivered to recipient token: {$token}");

        } elseif ($status !== 200 && $status !== null) {

            $this->log->addNotice("Message failed to recipient token: {$token} with status: {$status} and reason {$result}");
        } else {

            $this->log->addNotice("Message failed to send to recipient token: {$token} with no status/ unknown reason");
        }
    }

    /**
    * @param $result
    * @param $token
    */
    protected function addToFailedTokensIfFailed($http2ch, $token) {

        $result = curl_multi_getcontent($http2ch);

        if ($this->checkForFailedTokenResponse($result)) {
            
            $this->failedTokens[] = $token;
        }
    }

    /**
    * @param $result
    */
    protected function checkForFailedTokenResponse($result) {

        foreach (Push::$INVALID_TOKEN_RESPONSES as $invalid_response_reason) {
            
            if (strpos($result, $invalid_response_reason) !== false) {
                
                return true;
            }        
        }

        return false;
    }

    /**
     * @param $production
     * @param $certificate
     * @throws PushException
     */
    protected function verifyEnvironment($production, $certificate)
    {
        if ($production) {

            $this->server = static::PRODUCTION_SERVER;

        } else {

            $this->server = static::SANDBOX_SERVER;
        }

        if (!is_file($certificate)) {

            throw new PushException("Invalid certificate file location");
        }
    }
}