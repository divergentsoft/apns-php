<?php


class FeedbackTest extends PHPUnit_Framework_TestCase
{

    public function testFeedback()
    {
        $this->feedback = new \Push\Feedback();

        $this->feedback->connect(false, '/some/directory/ssl.pem');

        $errors = $this->feedback->read();
    }
}