<?php

namespace FastFast\Common\Notifications;

use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class FirebaseNotification
{
    private \Kreait\Firebase\Contract\Messaging $fcm;
    private Factory $factory;


    public function __construct()
    {
        $this->getFactory();
        $this->fcm = $this->getFirebaseInstance();
    }

    private function getFactory()
    {
        $factory = new Factory();
        if (app()->environment('testing')) {
            $http = HttpClientOptions::default()->withGuzzleConfigOption('base_uri', 'http://localhost:8080')
                ->withGuzzleConfigOption('verify', false);
            $factory = $factory->withHttpClientOptions($http);
        }
        $this->factory = $factory;
    }

    private function getFirebaseInstance()
    {
        return $this->factory->withServiceAccount(storage_path('app/firebase') .'/fastfast-firebase.json')->createMessaging();
    }
    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    private function validateFirebaseToken(Messaging $messaging, $device_tokens)
    {
        $result = $messaging->validateRegistrationTokens($device_tokens);
        $resp = [];
        if(isset($result['valid']))
        {
            $resp = $result['valid'];
        }
        return $resp;
    }


    private function generateFirebaseNotification($title, $body)
    {
        return \Kreait\Firebase\Messaging\Notification::create($title, $body);
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function sendUserMessage($devices, $data, $title, $body): Messaging\MulticastSendReport
    {
        $notification = $this->generateFirebaseNotification($title, $body);
        $messages = [];
        $cm = CloudMessage::new()->withData($data)->withNotification($notification);
        foreach ($devices as $device) {
            $tokens = $device['tokens'];
            $id = $device['id'];
            foreach ($tokens as $token) {
                $messages[] = $cm->toToken($token);
            }
        }

        return $this->fcm->sendAll($messages);
    }

    public function getToken(User $user, $type = 'android') {
        $devices = $user->devices->collect();
        if ($user->device_token && $user->device_type == $type) {
            $devices->push([
                'token' => $user->device_token,
                'type'=> $type
            ]);
        }
        return $devices->where('type', '=', $type)->pluck('token')->toArray();
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function sendRidersNotifications($order, $riders, $devices, $requests, $data, $metadata): Messaging\MulticastSendReport
    {
        $title = $metadata['title'];
        $body = $metadata['body'];
        $notification = $this->generateFirebaseNotification($title, $body);
        $messages = [];

        foreach ($riders as $rider) {
            $message = [
                'user_id' => $rider->user_id,
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'request_id' => $requests->where('rider_id', $rider->id)->first()->id,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
            ];
            $cm = CloudMessage::new()->withData($message)->withNotification($notification);
            $tokens = $devices[$rider->user_id];
            foreach ($tokens as $token) {
                $messages[] = $cm->toToken($token);
            }
        }

        return $this->fcm->sendAll($messages);
    }
}