<?php


class PushTest extends PHPUnit_Framework_TestCase
{

    /**
     * These will need to be set to some actual values for the tests to pass
     */
    const TEST_TOKEN = [
        'bc95b585a4a8bc8238bef87070eaf5ea56a7ac304c350073baebb2e1a8946a71',
        'eccfcfbfbb9592c3c3a46740254cb49865cdfa384ede7f93882665e141011609'
    ];

    const TEST_CERT = '/path/to/cert/cert.pem';

    public function setUp()
    {
        $this->push = new \Divergentsoft\Push();
    }

    /**
     * @expectedException Divergentsoft\PushException
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
        $message = new \Divergentsoft\Message();

        $message->initialize(static::TEST_TOKEN);

        $message->setAlert("ahhhhh");

        $message->getMessage();

        $this->push->connect(false, static::TEST_CERT);

        $this->push->send($message);
    }

}