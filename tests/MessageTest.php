<?php


class MessageTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {

        $this->message = new \Divergentsoft\Message("6eb29758 dc3d8a11 6df540eb b546e02d 6d63b34c a9498e5b 77a4c733 7ea939ca");
    }

    /**
     * @expectedException Divergentsoft\PushException
     */
    public function testBadTokenArray()
    {
        (new \Divergentsoft\Message())->initialize(["6eb29758 dc3d8a11 6df540eb hello 6d63b34c a9498e5b 77a4c733 7ea939ca", "6eb29758 dc3d8a11 6df540eb b546e02d 6d63b34c a9498e5b 77a4c733 7ea939ci"]);

    }

    /**
     * @expectedException Divergentsoft\PushException
     */
    public function testBadTokenString()
    {
        (new \Divergentsoft\Message())->initialize('hello');

    }

    public function testSetAlert()
    {
        $this->message->setAlert("message", "title");

        $testMessage = json_encode(['aps' => ['alert' => ['title' => 'title', 'body' => 'message']]]);

        $this->assertEquals($this->message->getMessage(), $testMessage);
    }

    public function testSetSilent()
    {
        $this->message->setSilent(true);

        $testMessage = json_encode(['aps' => ['content-available' => 1]]);

        $this->assertEquals($this->message->getMessage(), $testMessage);
    }

    public function testBadgeAndAlert()
    {
        $this->message->setAlert("message", "title");

        $this->message->setBadge(3);

        $testMessage = json_encode(['aps' => ['alert' => ['title' => 'title', 'body' => 'message'],'badge' => 3]]);

        $this->assertEquals($this->message->getMessage(), $testMessage);
    }

    public function testCustomProperty(){

        $this->message->setCustomProperty("podcasts",['podcast 1' => ['title' => 'some title','body'=> 'some body'], 'podcast 2' => ['title' => '', 'body' => '']]);

        $testMessage = json_encode(['podcasts' => ['podcast 1' => ['title' => 'some title','body'=> 'some body'], 'podcast 2' => ['title' => '', 'body' => '']]]);

       $this->assertEquals($this->message->getMessage(),$testMessage);
    }

    public function testMultipleCustomProperties(){

        $this->message->setSilent(true);

        $this->message->setCustomProperty("podcasts",['podcast 1' => ['title' => 'some title','body'=> 'some body'], 'podcast 2' => ['title' => '', 'body' => '']]);

        $this->message->setCustomProperty("screencasts",['screencast 1' => ['title' => 'some title','body'=> 'some body'], 'screencast 2' => ['title' => '', 'body' => '']]);

        $testMessage = json_encode(['aps'=> ['content-available' => 1],'podcasts' => ['podcast 1' => ['title' => 'some title','body'=> 'some body'], 'podcast 2' => ['title' => '', 'body' => '']],
            'screencasts' => ['screencast 1' => ['title' => 'some title','body'=> 'some body'], 'screencast 2' => ['title' => '', 'body' => '']]]);

        $this->assertEquals($this->message->getMessage(),$testMessage);
    }
}