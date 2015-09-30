<?php


namespace Push;


use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class Push
 * @package Push
 */
class Push
{

    /**
     * Apple's production gateway
     */
    const PRODUCTION_SERVER = 'ssl://gateway.push.apple.com:2195';

    /**
     * Apple's sandbox gateway
     */
    const SANDBOX_SERVER = 'ssl://gateway.sandbox.push.apple.com:2195';

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
     * Push constructor. Pass in the log location.
     * @param $logLocation
     */
    public function __construct($logLocation = __DIR__.'/../log.txt')
    {
        $this->log = new Logger('Push');

        if(!is_file($logLocation)){

            fopen( $logLocation,'a');
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
     * @throws PushException
     */
    public function connect($production, $certificate, $passphrase = null)
    {
        $this->production = $production;

        $this->certificate = $certificate;

        $this->passphrase = $passphrase;

        $this->verifyEnvironment($production, $certificate);

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

            return false;
        }

        $this->setStreamBlocking();

        return true;
    }

    /**
     * Send the push notification(s)
     *
     * @param Message $message
     */
    public function send(Message $message)
    {
        foreach ($message->recipients as $token) {

            $frameData =
                chr(1) . pack('n', 32) . pack('H*', $token) .
                chr(2) . pack('n', $message->getMessageSize()) . $message->encodedMessage .
                chr(3) . pack('n', 4) . pack('N', "123") .
                chr(4) . pack('n', 4) . pack('N', time() + 86400) .
                chr(5) . pack('n', 1) . chr(10);

            $msg = chr(2) . pack('N', strlen($frameData)) . $frameData;

            fwrite($this->client, $msg, strlen($msg));

            // TODO: This will need to be addressed
            /*
             * Currently waiting for 100ms to see if there is a response from Apple which
             * indicates the last push failed and silently closes the connection. We need to
             * restart the connection and keep going after the last failure when this happens.
             */
            usleep(100000);

            $apple_error_response = fread($this->client, 6);

            if ($apple_error_response == "") {

                $this->log->addNotice("Message sent");

            } else {

                $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);

                $this->log->addError("Push notification to: " . $token . " failed with error: " . $error_response['status_code']);

                $this->closeConnection();

                $this->connect($this->production,$this->certificate,$this->passphrase);

            }

        }

        $this->closeConnection();

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

    /**
     * Since Apple does not return any value on successful push notifications,
     * we need to set the blocking to 0 so that we don't wait for a response after
     * sending a message before moving on to the next one.
     */
    protected function setStreamBlocking()
    {
        stream_set_blocking($this->client, 0);
    }
}