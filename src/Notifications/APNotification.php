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
    public function sendRidersNotifications($order, $riders, $devices, $requests, $data, $metadata): array
    {
        $title = $metadata['title'];
        $body = $metadata['body'];
        $notifications = [];

        $apn = new CustomAPNNotification('rider'); // same app bundle id
        foreach ($riders as $rider) {
            $notifications['tokens'] = $devices[$rider->user_id];
            $notifications['data'] = [
                'user_id' => $rider->user_id,
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'request_id' => $requests->where('rider_id', $rider->id)->first()->id,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
            ];
        }

        return $apn->sendMultiMessages($notifications, $metadata);
    }
}