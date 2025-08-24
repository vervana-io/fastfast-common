<?php

namespace FastFast\Common\Notifications;

use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\InvalidPayloadException;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;

class APNotification
{
    private $options;
    private Client $client;

    public function __construct($user_type = "seller")
    {
        $app_bundle_id = match ($user_type) {
            'seller' => config('broadcasting.connections.apn.seller_bundle_id'),
            'rider' => config('broadcasting.connections.apn.rider_bundle_id'),
            'customer' => config('broadcasting.connections.apn.customer_bundle_id'),
            default => config('broadcasting.connections.apn.seller_bundle_id'),
        };

        $this->options = [
            'key_id' => config('broadcasting.connections.apn.key_id'),
            'team_id' => config('broadcasting.connections.apn.team_id'),
            'app_bundle_id' => $app_bundle_id,
            'private_key_path' => config('broadcasting.connections.apn.private_key_path'),
            'production' => match ($user_type) {
                'seller' => true,
                default => config('broadcasting.connections.apn.production')
            },
        ];
        $authProvider = AuthProvider\Token::create($this->options);
        $this->client = new Client($authProvider, $this->options['production']);
    }

    /**
     * @throws InvalidPayloadException
     */
    public function sendAll($data, $title, $body)
    {
        $alert = Alert::create()
            ->setTitle($title)
            ->setBody($body);

        $payload = Payload::create()
            ->setAlert($alert);
        $payload->setSound('default');
        $payload->setCustomValue('type', $title);
        foreach ($data as $item) {
            $payload->setCustomValue('data', $data['message']);
            foreach ($item['tokens'] as $token) {
                $this->client->addNotification(new Notification($payload, $token));
            }
        }

        $this->client->push();
    }

    /**
     * @throws InvalidPayloadException
     * @throws \Exception
     */
    public function sendUserMessage($tokens, $data, $title, $body): array
    {
        $alert = Alert::create()
            ->setTitle($title)
            ->setBody($body);

        $payload = Payload::create()
            ->setAlert($alert);
        $payload->setSound('default');
        $payload->setCustomValue('type', $title);
        $payload->setCustomValue('data', $data);
        foreach ($tokens as $token) {
            $this->client->addNotification(new Notification($payload, $token));
        }
        return $this->client->push();
    }

    /**
     * @throws InvalidPayloadException
     * @throws \Exception
     */
    public function sendRidersNotifications($order, $riders, $requests, $data, $metadata): array
    {
        $title = $metadata['title'];
        $body = $metadata['body'];
        $alert = Alert::create()
            ->setTitle($title)
            ->setBody($body);

        $payload = Payload::create()
            ->setAlert($alert);
        $payload->setSound('default');
        $payload->setCustomValue('type', $title);
        foreach ($riders as $rider) {
            $payload->setCustomValue('data', [
                'user_id' => $rider->user_id,
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'request_id' => $requests->where('rider_id', $rider->id)->first()->id,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
            ]);
            $tokens = $this->getToken($rider, 'ios');
            foreach ($tokens as $token) {
                $this->client->addNotification(new Notification($payload, $token));
            }
        }
        return $this->client->push();
    }
    public function getToken(User $user, $type = 'ios') {
        $devices = $user->devices->collect();
        if ($user->device_token && $user->device_type == $type) {
            $devices->push([
                'token' => $user->device_token,
                'type'=> $type
            ]);
        }
        return $devices->where('type', '=', $type)->pluck('token')->toArray();
    }
}