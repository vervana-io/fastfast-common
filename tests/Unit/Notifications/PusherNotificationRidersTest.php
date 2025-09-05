<?php

namespace Tests\Unit\Notifications;

use FastFast\Common\Notifications\PusherNotification;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class PusherNotificationRidersTest extends TestCase
{
    public function test_send_riders_notifications_chunks_and_waits()
    {
        // Prepare 21 riders to force 3 batches: 10, 10, 1
        $riders = new Collection();
        $requests = new Collection();
        for ($i = 1; $i <= 21; $i++) {
            $r = (object)['id' => $i, 'user_id' => $i + 100];
            $riders->push($r);
            $requests->push((object)['id' => $i * 1000, 'rider_id' => $i]);
        }

        $order = (object)['id' => 55];
        $data = ['foo' => 'bar'];
        $meta = ['title' => 'T', 'body' => 'B'];

        $waitCounter = 0;
        $captured = [];
        $pusher = $this->getMockBuilder(\Pusher\Pusher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['triggerBatchAsync'])
            ->getMock();
        $pusher->method('triggerBatchAsync')->willReturnCallback(function ($batch) use (&$waitCounter, &$captured) {
            $captured[] = $batch;
            $waitCounter++;
            return \GuzzleHttp\Promise\Create::promiseFor(true);
        });

        $notif = $this->getMockBuilder(PusherNotification::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $ref = new \ReflectionProperty(PusherNotification::class, 'pusher');
        $ref->setAccessible(true);
        $ref->setValue($notif, $pusher);

        $results = $notif->sendRidersNotifications($order, $riders, $requests, $data, $meta);

        $this->assertCount(3, $results);
        $this->assertSame(3, $waitCounter);

        // Assert channel naming and request id mapping on a sample item
        $firstBatch = $captured[0];
        $this->assertSame('orders.approved.101', $firstBatch[0]['channel']);
        $this->assertSame('rider_new_order', $firstBatch[0]['name']);
        $this->assertSame(1000, $firstBatch[0]['data']['order']['request_id']);
    }
}


