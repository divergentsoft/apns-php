<?php


class PushTest extends PHPUnit_Framework_TestCase
{

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

    public function testConnectionToSandbox(){

        $connection = $this->push->connect(false,'/Users/Rob/Code/laravel/oma-laravel/app/Certificates/server.pem');

        $this->assertTrue($connection);
    }

}