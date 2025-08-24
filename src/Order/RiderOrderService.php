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

class RiderOrderService extends OrderService implements FFOrderService
{
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

        $this->sender->sendAllMessages([
            $seller,
            $customer,
        ], $data, $title, $body, 'rider_delivery_accept');
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

    public function approved(Order $order, $exclude = [])
    {
        // TODO: Implement approved() method.
        return $this->sender->sendOrderApprovedNotification($order, $exclude);
    }
}