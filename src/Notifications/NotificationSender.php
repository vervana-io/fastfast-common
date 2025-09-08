<?php

namespace FastFast\Common\Notifications;

use FastFast\Common\Service\UserDeviceService;
use App\Models\Notification as Model_Notification;
use App\Models\Rider;
use App\Models\User;
use App\Models\Order;
use FastFast\Common\Firestore\FirestoreClient;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Pusher\ApiErrorException;
use Pusher\PusherException;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Rider_Order;

class NotificationSender
{

    private UserDeviceService $deviceService;
    public function __construct(
        private FirestoreClient $firestore,
        private PusherNotification $pusher,
        private FirebaseNotification $fcm,
        private APNotification $apns,
    )
    {
        $this->deviceService = new UserDeviceService();
    }

    public function createNotification($data)
    {
        return Model_Notification::create($data);
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function sendNotification(User $user, $data, $metadata )
    {
        //$this->createNotification($data);
        $title = $metadata['title'];
        $body =  $metadata['body'];
        $results = [];
        $devices = $this->deviceService->getTokens($user);
        $tokens = $devices['tokens'];
        if (isset($tokens['android'])) {
            $results['fcm'] = $this->fcm->sendToTokens($tokens['android'], $data, $title, $body);
        }
        if (isset($tokens['ios'])) {
            $results['apns'] = $this->apns->sendUserMessage($devices['type'],$tokens['ios'], $data, $title, $body);
        }
        $results['pusher'] = $this->pusher->sendUserMessage($user,$data, $metadata['event'], $metadata['channel'] ?? 'FastFast');

        return $results;
    }


    public function sendAllMessages($users, $data, $title, $body, $event)
    {
        $devices = $this->deviceService->getUsersDeviceTokens($users);
        $fcmDevices = $this->getTokens($devices, 'android');
        $apnDevices = $devices->map(function ($device) {
            return [
                'tokens' => $device['ios'],
                'userType' => $device['user_type'],
                'id' => $device['id'],
            ];
        })->filter(function ($device) {
            return count($device['tokens']) > 0;
        })->values()->toArray();


        $this->fcm->sendUserMessage($fcmDevices, $data, $title, $body);
        $this->apns->push($apnDevices, $data, $title, $body);
        $this->pusher->sendMessage($data, $event);
        return[];
    }

    public function sendOrderApprovedNotification(Order $order, $exclude = [])
    {
        try {
            $seller = $order->seller;
            $order_products = [];
            $average_prep_time = 0;
            $avptarr = [];
            $ordp = $order->order_products;
            if ($ordp->count() > 0) {
                foreach ($ordp as $ord) {
                    $order_products[] = [
                        'Quantity' => $ord->quantity,
                        'name' => $ord->product->title,
                    ];
                    $avptarr[] = $ord->product->prep_time;
                }
            }
            $average_prep_time = count($avptarr) > 0 ? array_sum($avptarr) / count($avptarr) : 0;
            $average_prep_time = round($average_prep_time);
            $customer_address = [
                'latitude' => $order->delivery_latitude,
                'longitude' => $order->delivery_longitude,
                'street' => $order->delivery_address,
            ];
            if ($order->is_gift == 1) {
                $customer_address['city'] = $order->receiver_city;
                $customer_address['house_number'] = $order->receiver_house_number;
            } else {
                $address = $order->address;
                $customer_address['city'] = $address?->city;
                $customer_address['house_number'] = $address?->house_number;
            }
            $seller_primary_address = $seller->primary_address;
            $seller_address = "";
            $seller_addr_arr = [];
            if (!is_null($seller_primary_address)) {
                $seller_addr_arr = [
                    'city' => $seller_primary_address->address,
                    'house_number' => $seller_primary_address->house_number,
                    'latitude' => $seller_primary_address->latitude,
                    'longitude' => $seller_primary_address->longitude,
                    'street' => $seller_primary_address->street,
                    'nearest_bus_stop' => $seller_primary_address->nearest_bus_stop,
                ];
            }
            $order_info = [
                'notification_name' => 'order_request',
                'status' => $order->status,
                'address' => $seller_addr_arr,
                'customer_address' => $customer_address,
                'amount' => $order->total_amount,
                'sub_total' => $order->sub_total,
                'delivery_fee' => $order->delivery_fee,
                'order_id' => $order->id,
                'orders' => $order_products,
                'time' => $average_prep_time . " minutes",
                'title' => $seller->name . " has an order",
                "trading_name" => $seller->trading_name,
                'reference' => $order->reference,
            ];
            $primary_address = $seller->primary_address;
            return $this->notifyRiders($order, $order_info, $primary_address, 1000, $exclude);

        }catch (\Exception $e) {
            throw $e;
        }
    }


    /**
     * @throws PusherException
     * @throws ApiErrorException
     */
    public function notifyRiders(Order $order, $orderInfo, $primaryAddress, $distance = 5, $exclude = [])
    {
        if(!$primaryAddress)
        {
            return false;
        }
        $riders = $this->getNearestRiders($order->seller->id, $primaryAddress->latitude, $primaryAddress->longitude, $distance, $exclude, true);
        if ($riders->count() < 1) {
            return $this->notifyRiders($order, $orderInfo, $primaryAddress, $distance + 2, $exclude);
        }

        return $this->sendRidersNotifications($order, $riders, $orderInfo);
    }

    /**
     * @throws PusherException
     * @throws ApiErrorException
     */
    private function sendRidersNotifications(Order $order, $riders, $data, $meta = [])
    {
        $seller = $order->seller;
        $title = 'New Order';
        $body = "New Order $order->reference for $seller->name at $seller->address";
        $requests = $riders->map(fn($rider) => [
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();
        $inserted = Rider_Order::query()->insert($requests);
        $requests = Rider_Order::query()->where('order_id', $order->id)->get();
        $metadata = [
            'title' => $title,
            'body' => $body
        ];
        $devices = $this->deviceService->getUsersDeviceTokens($riders->pluck('user'));
        $ios = $this->getTokens($devices, 'ios')->mapWithKeys(function ($device) {
            return [$device['id'] => $device['tokens']];
        })->toArray();
        $android = $this->getTokens($devices, 'android')->mapWithKeys(function ($device) {
            return [$device['id'] => $device['tokens']];
        })->toArray();

        $response = [];
        //$response['firestore'] = $this->firestore->addRiderOrderDocuments($order, $riders, $reques  ts, $data, $metadata);
        $response['pusher'] = $this->pusher->sendRidersNotifications($order, $riders, $requests, $data, $metadata);
        $response['fcm'] = $this->fcm->sendRidersNotifications($order, $riders, $android, $requests, $data, $metadata);
        $response['apns'] = $this->apns->sendRidersNotifications($order, $riders, $ios, $requests, $data, $metadata );

        return $response;
    }

    private function getTokens($devices, $type)
    {
        return $devices->filter(function ($device) use($type){
            return !empty($device[$type]) && count($device[$type]) > 0;
        })->map(function ($device) use($type) {
            return [
                'id' => $device['id'],
                'tokens' => $device[$type]
            ];
        });
    }

    public function getNearestRiders($id, $latitude, $longitude, $distance = 5, $excludes = [], $prodTest = false)
    {
        if ($prodTest) {
            if (app()->environment('production') and !empty(config('dev_test.test_sellers')) and in_array($id, config('dev_test.test_sellers'))) {
                $key = "dev_test.seller.$id";
                $rider_id = config($key);
                $riders = Rider::whereHas('user')->whereIn('id', [$rider_id])->get();
                if ($riders->count() > 0) {
                    return $riders;
                }
            }
        }
        $haversine = "(
            6371 * acos(cos(radians($latitude)) *
                cos(radians(current_latitude)) *
                cos(radians(current_longitude) -
                radians($longitude)) +
                sin(radians($latitude)) *
                sin(radians(current_latitude)))
            )";
        return Rider::whereHas('user', function(Builder $builder) use ($haversine, $distance){
            $builder->selectRaw("$haversine AS distance")->havingRaw("distance < ?", [$distance])->orderBy('distance');
        })->where('status', '=', 1)
            ->whereNotIn('id', $excludes)
            ->get();
    }
}