<?php

namespace FastFast\Common\Order;


use App\Jobs\NotifyAvailableRiders;
use App\Models\Rider;
use App\Models\Order;
use FastFast\Common\Firestore\FirestoreClient;
use FastFast\Common\Notifications\APNotification;
use FastFast\Common\Notifications\CustomAPNNotification;
use FastFast\Common\Notifications\FirebaseNotification;
use FastFast\Common\Notifications\NotificationSender;
use FastFast\Common\Notifications\PusherNotification;
use FastFast\Common\Service\FFOrderService;
use Illuminate\Database\Eloquent\Builder;
use Pusher\ApiErrorException;
use Pusher\PusherException;

class RiderOrderService implements FFOrderService
{

    private NotificationSender $sender;
    public function __construct(
        private FirestoreClient $firestore,
        private PusherNotification $pusher,
        private FirebaseNotification $fcm,
        private APNotification $apns,
    )
    {
    }

    public function approved(Order $order, $exclude = [])
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
            $this->notifyRiders($order, $order_info, $primary_address, 1000);

        }catch (\Exception $e) {
        }
    }

    public function send($order)
    {


    }
    public function accepted(Order $order)
    {
        //send to seller, customer,admin
        $customer = $order->customer;
        $seller = $order->seller;
        $rider = $order->rider;
        $title = 'Delivery Acceptance';
        $body = "$rider->full_name has accepted the request to deliver the order $order->reference and is on the way.";
        //$customer_user_id = $customer->user_id;
        $data = [
            'user_id' => $customer->user_id,
            'order_id' => $order->id,
            'title' => $title,
            'body' => $body
        ];

        $sellerAndroidDevices = $this->fcm->getToken($seller->user);
        $sellerIosDevices = $this->fcm->getToken($seller->user, 'ios');
        $customerAndroidDevices = $this->fcm->getToken($customer->user,);
        $customerIosDevices = $this->fcm->getToken($customer->user, 'ios');
        $fcmDevices = collect()->push($sellerAndroidDevices)->push($customerAndroidDevices)->toArray();
        $apnDevices = collect()->push($sellerIosDevices)->push($customerIosDevices)->toArray();
        $this->fcm->sendUserMessage($fcmDevices, $data, $title, $body);
        $this->apns->sendUserMessage($apnDevices,$data, $title, $body);
        $this->pusher->sendMessage($data, 'rider_delivery_accept');
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

        $this->firestore->addRiderOrderDocuments($order, $riders, $requests, $data, $metadata);
        $this->pusher->sendRidersNotifications($order, $riders, $requests, $data, $metadata);
       $this->fcm->sendRidersNotifications($order, $riders, $requests, $data, $metadata);
        $this->apns->sendRidersNotifications($order, $riders, $requests, $data, $metadata );

        return true;
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

    public function created(Order $order, $tranxn)
    {
        // TODO: Implement created() method.
    }

    public function verified(Order $order, $transId)
    {
        // TODO: Implement verified() method.
    }

    public function canceled(Order $order, $transId, $reason)
    {
        // TODO: Implement canceled() method.
    }

    public function rejected(Order $order, $rider)
    {
        return $this->approved($order, [$rider->id]);
    }

    public function delivered(Order $order)
    {
        // TODO: Implement delivered() method.
    }

    public function ready(Order $order)
    {
        try {

            $seller = $order->seller;
            $rider = $order->rider;
            if(!$rider) {
                $this->info('Order has no rider');
                return true;
            }
            $order_products = [];
            $ordp = $order->order_products;
            if ($ordp->count() > 0) {
                foreach ($ordp as $ord) {
                    $order_products[] = [
                        'Quantity' => $ord->quantity,
                        'name' => $ord->product->title,
                    ];
                }
            }
            $customer_address = [];
            if ($order->is_gift == 1) {
                $customer_address = [
                    'city' => $order->receiver_city,
                    'house_number' => $order->receiver_house_number,
                    'latitude' => $order->receiver_latitude,
                    'longitude' => $order->receiver_longitude,
                    'street' => $order->receiver_street,
                ];
            } else {
                $address = $order->address;
                $customer_address = [
                    'city' => $address?->city,
                    'house_number' => $address?->house_number,
                    'latitude' => $address?->latitude,
                    'longitude' => $address?->longitude,
                    'street' => $address?->street,
                ];
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
                $seller_address = $seller_primary_address->house_number . " " . $seller_primary_address->street . " ";
            }
            $order_info = [
                'notification_name' => 'order_pickup',
                'status' => $order->status,
                'address' => $seller_addr_arr,
                'customer_address' => $customer_address,
                'amount' => $order->total_amount,
                'sub_total' => $order->sub_total,
                'delivery_fee' => $order->delivery_fee,
                'order_id' => $order->id,
                'orders' => $order_products,
                'title' => $seller->name . " has an order",
                "trading_name" => $seller->trading_name,
            ];
            $title = 'Order Pick Up';
            $body = "Order $order->reference for $seller->name at $seller_address is ready for pick up";
            $main_data = [
                'user_id' => $rider->user_id,
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($order_info),
            ];

        }catch (\Exception $e) {
            return false;
        }
    }
}