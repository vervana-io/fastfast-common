<?php

namespace FastFast\Common\Notifications;

use App\Models\User;
use Pushok\InvalidPayloadException;
use Exception;

class APNotification
{

    public function push($devices, $data, $title, $body)
    {
        $results = [];
        foreach ($devices as $device) {
            $tokens = $device['tokens'];
            $results[] = $this->sendUserMessage($device['userType'], $tokens, $data, $title, $body);
        }
        return $results;
    }
    /**
     * @throws InvalidPayloadException
     * @throws Exception
     */
    public function sendUserMessage($type, $tokens, $data, $title, $body): array
    {
        $data['title'] = $title;
        $data['body'] = $body;
        $apn = new CustomAPNNotification($type);
        return $apn->sendMultiDeviceNotification($tokens, $data);
    }

    /**
     * @throws InvalidPayloadException
     * @throws Exception
     */
    public function sendRidersNotifications($order, $riders, $requests, $data, $metadata): array
    {
        $title = $metadata['title'];
        $body = $metadata['body'];
        $notification = [
            'title' => $title,
            'body' => $body,
        ];

        $results = [];
        //TODO: to make this parallel
        foreach ($riders as $rider) {
            $tokens = $this->getToken($rider->user);
            $notification[] = [
                'user_id' => $rider->user_id,
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'request_id' => $requests->where('rider_id', $rider->id)->first()->id,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
            ];
            $apn = new CustomAPNNotification('rider');
            $results[$rider->id] = $apn->sendMultiDeviceNotification($tokens, $notification);
        }

        return $results;
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