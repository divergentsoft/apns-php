<?php


class PushTest extends PHPUnit_Framework_TestCase
{

    /**
     * These will need to be set to some actual values for the tests to pass
     */
    const TEST_TOKEN = '6eb29758 dc3d8a11 6df540eb b546e02d 6d63b34c a9498e5b 77a4c733 7ea939ca';

    const TEST_CERT = 'cert.pem';

    public function setUp()
    {

        $this->push = new \Push\Push();
    }

    /**
     * @expectedException Push\PushException
     */
    public function testBadCertificateLocation()
    {
        $this->push->connect(false, '/some/dir/code/ssl/cert.pem');

    }

    public function testConnectionToSandbox()
    {

        $connection = $this->push->connect(false, static::TEST_CERT);

        $this->assertTrue($connection);
    }

    public function testPush()
    {
        $message = new \Push\Message(static::TEST_TOKEN);

        $message->setAlert("ahhhhh");

        $message->getMessage();

        $this->push->connect(false, static::TEST_CERT);

        $this->push->send($message);

    }

}