<?php

namespace FastFast\Common\Order;

use Error;
use FastFast\Common\Service\FFOrderService;
use App\Models\Order;
use App\Models\Rider;

class SellerOrderService extends OrderService implements FFOrderService
{

    public function created(Order $order, $tranxn): mixed
    {
        throw new \Exception(
        'Seller cannot create an order');
    }

    public function verified(Order $order, $transId): mixed
    {
        $customer = $order->customer;
        $title = 'Order Verification';
        $seller = $order->seller;
        $body = 'Your Order ' . $order->reference . " needs your verification";
        $not_data = [
            'user_id' => $customer->user_id,
            'order_id' => $order->id,
            'title' => $title,
            'body' => $body
        ];
        $this->sender->createNotification($not_data);
        $not_data['seller_id'] = $seller->id;
        $not_data['seller_name'] = $seller->name;
        return $this->sender->sendNotification($customer->user, $not_data, [
            'title' => $title,
            'body' => $body,
            'event' => 'verify_order',
            'status' => 'verified',
        ]);
    }

    public function approved(Order $order, $exclude = []): mixed
    {
        $title = 'Order Confirmed';
        $biz_name = $order->seller->name;
        $customer = $order->customer;
        $body = "$biz_name has accepted your order and is now preparing it";
        $not_data = [
            'title' => $title,
            'body' => $body,
            'order_id' => $order->id,
            'type' => 'order_accepted',
        ];
        $this->sender->sendNotification($customer->user, $not_data, [
            'title' => $title,
            'body' => $body,
            'event' => 'order_approved',
            'status' => 'approved',
        ]);
        return $this->sender->sendOrderApprovedNotification($order, $exclude);
    }

    public function canceled(Order $order, $transId, $reason): mixed
    {
        $customer = $order->customer;
        $user = $customer->user;
        $title = 'Order Cancellation';
        $body = 'Order ' . $order->reference . " has been cancelled, reason: " . $reason;
        $not_data = [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'transaction_id' => $transId,
            'title' => $title,
            'body' => $body
        ];
        $not_data['seller_id'] = $order->seller_id;
        $this->sender->createNotification($not_data);
        //$this->send_user_order_cancel_email($user, $order);
        return $this->sender->sendNotification($user, $not_data,[
           'title' => $title,
            'body' => $body,
            'event' => 'order_canceled',
            'status' => 'canceled'

        ]);

    }

    public function rejected(Order $order, Rider $rider): mixed
    {
        throw new \Exception(
            'Seller cannot create an order');
    }

    public function delivered(Order $order): mixed
    {
        throw new \Exception(
            'Seller cannot create an order');
    }

    public function ready(Order $order): mixed
    {
        $seller = $order->seller;
        $rider = $order->rider;
        if(!$rider) {
            return $this->approved($order);
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
        return $this->sender->sendNotification($rider->user, $main_data, [
            'title' => $title,
            'body'  => $body
        ]);
    }

    public function delayed(Order $order, $time): mixed
    {
        $title = 'Order Delay';
        $body = 'Preparation of order ' . $order->reference . " by " . $order->seller->name . " would be delayed by " . $time . " minutes.";
        $not_data = [
            'user_id' => $order->customer->user_id,
            'order_id' => $order->id,
            'title' => $title,
            'body' => $body
        ];
        $this->sender->createNotification($not_data);
        $not_data['seller_id'] = $order->seller->id;
        $not_data['seller_name'] = $order->seller->name;
        return $this->sender->sendNotification($order->customer->user, $not_data,[
            'title' => $title,
            'body'  => $body
        ]);
    }

    public function accepted(Order $order): mixed
    {
        throw new Error('Seller cannot accept');
    }

    public function pickup(Order $order): mixed
    {
        throw new Error('Seller cannot accept');
    }

    public function arrived(Order $order, string $at): mixed
    {
        throw new Error('Seller cannot accept');
    }
}