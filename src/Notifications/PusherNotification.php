<?php

namespace FastFast\Common\Notifications;

use GuzzleHttp\Exception\GuzzleException;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;

class PusherNotification
{

    private Pusher $pusher;

    /**
     * @throws PusherException
     */
    public function __construct()
    {
        $this->pusher = new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'), [
            'cluster' => env('PUSHER_APP_CLUSTER'),
        ]);
    }

    private function getPusherInstance()
    {
        return new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'), [
            'cluster' => env('PUSHER_APP_CLUSTER'),
        ]);
    }

    /**
     * @throws PusherException
     * @throws GuzzleException
     * @throws ApiErrorException
     */
    public function sendPusherMessage($event, $data=[], $channel = "FastFast")
    {
        $pusher_instance = $this->getPusherInstance();
        return $pusher_instance->trigger($channel, $event, $data);
    }

    /**
     * @throws PusherException
     * @throws ApiErrorException
     */
    public function sendBatchPusher(array $batch)
    {
        return $this->getPusherInstance()->triggerBatchAsync($batch)->wait();
    }

    /**
     * @throws PusherException
     */
    public function sendRidersNotifications($order, $riders, $requests, $data, $metadata, $event = 'rider_new_order')
    {
        $title = $metadata['title'];
        $body = $metadata['body'];
        $pusherBatch = $riders->map(fn($rider) => [
            'channel' => "orders.approved.$rider->user_id",
            'name' => $event,
            'data' => [
                'order' => [
                    'user_id' => $rider->user_id,
                    'order_id' => $order->id,
                    'rider_id' => $rider->id,
                    'request_id' => $requests->where('rider_id', $rider->id)->first()->id,
                    'title' => $title,
                    'body' => $body,
                    'data' => json_encode($data),
                ]
            ]
        ]);

        return $this->pusher->triggerBatchAsync($pusherBatch->toArray())->wait();
    }

    /**
     * @throws PusherException
     * @throws ApiErrorException
     * @throws GuzzleException
     */
    public function sendUserMessage($user, $data, $event, $channel = 'FastFast'): object
    {
        $channel = "$channel.$user->id";
        return $this->pusher->trigger($channel, $event, $data);
    }

    public function sendMessage($data, $event, $channel = 'FastFast')
    {
        return $this->pusher->trigger($channel, $event, $data);
    }
}