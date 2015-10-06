<?php

namespace Push;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Class Feedback
 * @package Push
 */
class Feedback
{

    /**
     * Total length in Bytes of the response tuple
     */
    const FEEDBACK_RESPONSE_LENGTH = 38;
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
    protected $client;

    /**
     * @var
     */
    protected $server;

    /**
     * Apple's feedback sandbox
     */
    const SANDBOX_FEEDBACK_SERVER = 'ssl://feedback.sandbox.push.apple.com:2196';

    /**
     *  Production server
     */
    const FEEDBACK_SERVER = 'ssl://feedback.push.apple.com:2196';


    /**
     * Push constructor. Pass in the log location.
     * @param $logLocation
     */
    public function __construct($logLocation = __DIR__ . '/../log.txt')
    {
        $this->log = new Logger('Push');

        if (!is_file($logLocation)) {

            fopen($logLocation, 'a');
        }

        if ($logLocation != null) {

            $this->log->pushHandler(new StreamHandler($logLocation), Logger::WARNING);
        }
    }

    /**
     * @param $production
     * @param $certificate
     * @param null $passphrase
     * @return bool
     * @throws PushException
     */
    public function connect($production, $certificate, $passphrase = null)
    {
        $this->production = $production;

        $this->certificate = $certificate;

        $this->passphrase = $passphrase;

        $this->verifyFeedbackEnvironment($production, $certificate);

        $ctx = $this->configureContext($certificate, $passphrase);

        $this->client = stream_socket_client(
            $this->server,
            $err,
            $errstr,
            60,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
            $ctx
        );

        if (!$this->client) {

            throw new PushException('Failed to connect to APNS');
        }


        return true;
    }

    /**
     * Read the feedback service and return the tokens that have been previously
     * valid but are no longer in use.
     *
     * @return array The tokens received from the feedback service
     */
    public function read()
    {

        $failedTokens = [];

        while (!feof($this->client)) {

            $apple_error_response = fread($this->client, 8192);

            if ($apple_error_response == "") {

                $this->log->addNotice("No errors on the feedback service");

            } else {

                $length = strlen($apple_error_response);

                $tuples = $length / static::FEEDBACK_RESPONSE_LENGTH;

                for ($i = 0; $i < $tuples; $i++) {

                    $response = substr($apple_error_response, $i * static::FEEDBACK_RESPONSE_LENGTH, static::FEEDBACK_RESPONSE_LENGTH);

                    $error_response = unpack('Ntime/nlength/H*token', $response);

                    $failedTokens[] = $error_response['token'];

                    $this->log->addError("Push notification to: " . $error_response['token'] . " failed at time: " . $error_response['time']);

                }

            }

        }

        $this->closeConnection();

        return $failedTokens;

    }

    /**
     * Close the socket stream
     */
    protected function closeConnection()
    {
        fclose($this->client);
    }

    /**
     * @param $production
     * @param $certificate
     * @throws PushException
     */
    protected function verifyFeedbackEnvironment($production, $certificate)
    {
        if ($production) {

            $this->server = static::FEEDBACK_SERVER;

        } else {

            $this->server = static::SANDBOX_FEEDBACK_SERVER;
        }

        if (!is_file($certificate)) {

            throw new PushException("Invalid certificate file location");
        }
    }

    /**
     * @param $certificate
     * @param $passphrase
     * @return resource
     */
    protected function configureContext($certificate, $passphrase)
    {
        $ctx = stream_context_create();

        stream_context_set_option($ctx, 'ssl', 'local_cert', $certificate);

        stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);

        return $ctx;
    }
}