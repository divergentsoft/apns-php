<?php

namespace Push;


/**
 * Class Message used to construct the APNS payload
 * @package Push
 */
class Message
{
    /**
     *
     */
    const APPLE_NAMESPACE = "aps";

    /**
     * @var array to hold all recipients
     */
    public $recipients = [];

    /**
     * @var array
     */
    protected $message = [];

    /**
     * @var
     */
    public $encodedMessage;

    /**
     * @var
     */
    protected $alertMessage;

    /**
     * @var
     */
    protected $alertTitle;

    /**
     * @var
     */
    protected $badge;

    /**
     * @var
     */
    protected $sound;

    /**
     * @var bool
     */
    protected $contentAvailable = false;

    /**
     * @var array
     */
    protected $customProperties;


    /**
     * Pass in the tokens as either an array or single value
     */
    public function __construct($tokens)
    {
        $this->validateTokens($tokens);

        $this->message[static::APPLE_NAMESPACE] = "";

    }

    /**
     * @return mixed The JSON encode message
     */
    public function getMessage()
    {
        $this->buildMessage();

        $this->toJson();

        return $this->encodedMessage;
    }

    /**
     * @return int The size of the encoded message
     */
    public function getMessageSize()
    {
        return strlen($this->encodedMessage);

    }

    /**
     * @param $message The alert message
     * @param null $title Optional title
     */
    public function setAlert($message, $title = null)
    {
        $this->alertMessage = $message;

        $this->alertTitle = $title;
    }

    /**
     * @param $number The number to display on the badge
     */
    public function setBadge($number)
    {
        $this->badge = $number;
    }

    /**
     * @param $file
     */
    public function setSound($file)
    {
        $this->sound = $file;
    }

    /**
     * @param $value Don't display the notification if true
     */
    public function setSilent($value)
    {
        $this->contentAvailable = $value;
    }

    /**
     * @param $name
     * @param $value
     */
    public function setCustomProperty($name, $value)
    {
        $this->customProperties[$name] = $value;

    }

    /**
     * @param $tokens
     * @throws PushException
     */
    protected function validateTokens($tokens)
    {
        $token = "";

        if (is_array($tokens) && sizeof($tokens) > 0) {

            foreach ($tokens as $token) {

                $token = $this->removeTokenSpaces($token);

                $this->validateHexValue($token);

                $this->recipients[] = $token;
            }

        } else if (is_string($tokens)) {

            $token = $this->removeTokenSpaces($tokens);

            $this->validateHexValue($token);

            $this->recipients[] = $token;

        } else {

            throw new PushException("Invalid format for token: $token");
        }


    }

    /**
     * @param $token
     * @return mixed
     */
    protected function removeTokenSpaces($token)
    {
        return str_replace(' ', '', $token);

    }

    /**
     * @param $token
     * @throws PushException
     */
    protected function validateHexValue($token)
    {
        if (empty($token) || !ctype_xdigit($token) || strlen($token) != 64) {

            throw new PushException("Invalid format for token: $token");
        }

    }

    /**
     *
     */
    protected function buildMessage()
    {


        if (isset($this->alertMessage) && !isset($this->alertTitle)) {

            $this->message[static::APPLE_NAMESPACE]['alert'] = $this->alertMessage;
        }

        if (isset($this->alertTitle)) {

            $this->message[static::APPLE_NAMESPACE]['alert'] = ["title" => $this->alertTitle, "body" => $this->alertMessage];
        }

        if (isset($this->badge)) {

            $this->message[static::APPLE_NAMESPACE]['badge'] = $this->badge;
        }

        if (isset($this->sound)) {

            $this->message[static::APPLE_NAMESPACE]['sound'] = $this->sound;
        }

        if ($this->contentAvailable) {

            $this->message[static::APPLE_NAMESPACE]['content-available'] = 1;
        }

        if (isset($this->customProperties)) {

            foreach ($this->customProperties as $name => $value) {

                $this->message[$name] = $value;
            }

        }

    }

    /**
     *
     */
    protected function toJson()
    {
        $this->encodedMessage = json_encode($this->message);

    }


}