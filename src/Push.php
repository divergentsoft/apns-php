<?php


namespace Push;


class Push
{

    const PRODUCTION_SERVER = 'ssl://gateway.push.apple.com:2195';

    const SANDBOX_SERVER = 'ssl://gateway.sandbox.push.apple.com:2195';

    private $client;

    private $server;

    public function connect($production, $certificate, $passphrase = null)
    {
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

    public function send(Message $message)
    {
        $body["aps"] = array(
            "content-available" => 1,
            "badge" => 0,
            "alert" => "some alert!"
        );

        $this->body = $body;
        // Encode the payload as JSON
        $payload = json_encode($this->body);

        $lastToken = "";

        foreach ($this->tokens as $token){

            $frameData =
                chr(1) . pack('n', 32) . pack('H*', $token) .
                chr(2) . pack('n', strlen($payload)) . $payload .
                chr(3) . pack('n', 4) . pack('N',"123") .
                chr(4) . pack('n', 4) . pack('N', time() + 86400) .
                chr(5) . pack('n', 1) . chr(10);
            $msg = chr(2) . pack('N', strlen($frameData)) . $frameData;

            // Send it to the server

            $totalBytes = fwrite($this->fp, $msg, strlen($msg));

            if($totalBytes == 0){
                echo "disconnected! $lastToken";
            }
            $lastToken = $token;

            usleep(50000);

            $apple_error_response = fread($this->fp, 6);

            if ($apple_error_response == "") {
                \Log::info("Sent notification to: " . $token);
            } else {
                $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);
                \Log::warning("Push notification to: " . $token . " failed with error: " . $error_response['status_code']);

                $this->closeConnection();

                $this->openConnection();

            }

        }



        $this->closeConnection();

    }

    protected function closeConnection()
    {
        fclose($this->client);
    }

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

    protected function configureContext($certificate, $passphrase)
    {
        $ctx = stream_context_create();

        stream_context_set_option($ctx, 'ssl', 'local_cert', $certificate);

        stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);

        return $ctx;
    }

    protected function setStreamBlocking()
    {

        stream_set_blocking($this->client, 0);
    }
}