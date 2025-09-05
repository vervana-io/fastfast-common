<?php

namespace Tests\Unit\Notifications;

use FastFast\Common\Notifications\Notification;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    public function test_send_pusher_message_default_channel()
    {
        $pusher = $this->getMockBuilder(\Pusher\Pusher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trigger'])
            ->getMock();
        $pusher->expects($this->once())->method('trigger')->with('FastFast', 'evt', ['d' => 1]);

        $notif = new class($pusher) extends Notification {
            private $mock;
            public function __construct($mock) { $this->mock = $mock; }
            public function sendPusherMessage($event, $data = [], $channel = "FastFast")
            {
                return $this->mock->trigger($channel, $event, $data);
            }
        };
        $notif->sendPusherMessage('evt', ['d' => 1]);
        $this->assertTrue(true);
    }
}


