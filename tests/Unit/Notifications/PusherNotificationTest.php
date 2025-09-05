<?php

namespace Tests\Unit\Notifications;

use FastFast\Common\Notifications\PusherNotification;
use PHPUnit\Framework\TestCase;

class PusherNotificationTest extends TestCase
{
    public function test_send_message_triggers_event()
    {
        $pusher = $this->getMockBuilder(\Pusher\Pusher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trigger'])
            ->getMock();
        $pusher->expects($this->once())->method('trigger')->with('FastFast', 'evt', ['x' => 1]);

        $notif = $this->getMockBuilder(PusherNotification::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionProperty(PusherNotification::class, 'pusher');
        $ref->setAccessible(true);
        $ref->setValue($notif, $pusher);

        $notif->sendMessage(['x' => 1], 'evt');
    }
}


