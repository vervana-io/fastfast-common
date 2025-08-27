<?php

namespace FastFast\Common\Notifications;

use App\Models\Notification as Model_Notification;
use App\Models\Rider;
use App\Models\User;
use FastFast\Common\Firestore\FirestoreClient;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Pusher\ApiErrorException;
use Pusher\PusherException;

class NotificationSender
{

    public function __construct(
        private FirestoreClient $firestore,
        private PusherNotification $pusher,
        private FirebaseNotification $fcm,
        private APNotification $apns,
    )
    {
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
        $fcm =$this->fcm->sendUserMessage($this->getToken($user), $data, $title, $body);
        $ios = $this->getToken($user, 'ios');
        if (!empty($ios)) {
            $type = $user->user_type == 1 ? 'customer' : ($user->user_type == 3 ? 'rider' : 'seller');
            $results['apns'] = $this->apns->sendUserMessage($type,$this->getToken($user, 'ios'), $data, $title, $body);
        }
        $results['pusher'] = $this->pusher->sendUserMessage($user,$data, $metadata['event'], $metadata['channel'] ?? 'FastFast');

        return $results;
    }

    public function getToken(User $user, $type = 'android') {
        $devices = $user->devices?->collect();
        if (!$devices) {
            return [];
        }
        if ($user->device_token && $user->device_type == $type) {
            $devices->push([
                'token' => $user->device_token,
                'type'=> $type
            ]);
        }
        return $devices->where('type', '=', $type)->pluck('token')->toArray();
    }


    public function sendAllMessages($users, $data, $title, $body, $event)
    {
        $fcmDevices = collect();
        $apnDevices = collect();
        foreach ($users as $user) {
            $androidDevices = $this->getToken($user);
            $iosDevices = $this->getToken($user, 'ios');
            $fcmDevices->push($androidDevices);

            $apnDevices->push([
                'tokens' => $iosDevices,
                'userType' => $user->user_type == 1 ? 'customer' : ($user->user_type == 3 ? 'rider' : 'seller'),
                'id' => $user->id,
            ]);
        }

        $this->fcm->sendUserMessage($fcmDevices->toArray(), $data, $title, $body);
        $this->apns->push($apnDevices->toArray(),$data, $title, $body);
        $this->pusher->sendMessage($data, $event);
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
            $average_prep_time = array_sum($avptarr) / count($avptarr);
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
            return $this->notifyRiders($order, $order_info, $primary_address, 1000);

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
        $riders = $this->getNearestRiders($order->seller->id, $primaryAddress->latitude, $primaryAddress->longititude, $distance, $exclude, true);
        if ($riders->count() < 1) {
            return $this->notifyRiders($order->seller->id,$primaryAddress->latitude, $primaryAddress->longititude, $distance + 2, $exclude);
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

        $response = [];
        $response['firestore'] = $this->firestore->addRiderOrderDocuments($order, $riders, $requests, $data, $metadata);
        $response['pusher'] = $this->pusher->sendRidersNotifications($order, $riders, $requests, $data, $metadata);
        $response['fcm'] = $this->fcm->sendRidersNotifications($order, $riders, $requests, $data, $metadata);
        $response['apns'] = $this->apns->sendRidersNotifications($order, $riders, $requests, $data, $metadata );

        return $response;
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