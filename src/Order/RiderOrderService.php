<?php

namespace FastFast\Common\Order;


use App\Jobs\NotifyAvailableRiders;
use App\Models\Rider;
use App\Models\Order;
use App\Models\User;
use FastFast\Common\Firestore\FirestoreClient;
use FastFast\Common\Notifications\APNotification;
use FastFast\Common\Notifications\CustomAPNNotification;
use FastFast\Common\Notifications\FirebaseNotification;
use FastFast\Common\Notifications\NotificationSender;
use FastFast\Common\Notifications\PusherNotification;
use FastFast\Common\Service\FFOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Pusher\ApiErrorException;
use Pusher\PusherException;

class RiderOrderService extends OrderService implements FFOrderService
{
    public function accepted(Order $order): mixed
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
        $meta = [
            'title' => $title,
            'body' => $body,
            'event' => 'customer_pick_up_order',
        ];
        \Sentry\captureMessage('Sending notifications to customer and seller', \Sentry\Severity::info());
        $c = $this->sender->sendNotification($customer->user, $data, $meta);
        $s = $this->sender->sendNotification($seller->user, $data, $meta);

        \Sentry\captureMessage(json_encode(['customer response' => $c]), \Sentry\Severity::info());
        \Sentry\captureMessage(json_encode(['seller response' => $s]), \Sentry\Severity::info());
        $users = User::query()->whereIn('id', [$customer->user_id, $seller->user_id])->get();
        return $this->sender->sendAllMessages($users, $data, $title, $body, 'rider_delivery_accept');
    }

    public function created(Order $order, $tranxn): mixed
    {
        throw new \Exception(
            'Rider cannot create an order');
    }

    public function verified(Order $order, $transId): mixed
    {
        throw new \Exception(
            'Rider cannot verify an order');
    }

    public function canceled(Order $order, $transId, $reason): mixed
    {
        throw new \Exception(
            'Rider cannot cancel an order');
    }

    public function rejected(Order $order, $rider): mixed
    {
        $customer = $order->customer;
        $seller = $order->seller;
        //$rider = $order->rider;
        $title = 'Delivery Rejection';
        $body = "$rider->full_name has rejected the request to deliver the order $order->reference";
        $data = [
            'user_id' => $customer->user_id,
            'order_id' => $order->id,
            'title' => $title,
            'body' => $body
        ];
        $users = User::query()->whereIn('id', [$customer->user_id, $seller->user_id])->get();
        $this->sender->sendAllMessages($users, $data, $title, $body, 'rider_delivery_rejected');
        return $this->approved($order, [$rider->id]);
    }

    public function delivered(Order $order): mixed
    {
        $title = 'Order Delivered';
        $body = 'Order ' . $order->reference . " has been delivered successfully.";
        $customer = $order->customer;
        $seller = $order->seller;
        $rider = $order->rider;
        $data = [
            'user_id' => $customer->user_id,
            'order_id' => $order->id,
            'title' => $title,
            'body' => $body,
            'rider_id' => $rider->id,
            'seller_id' => $seller->id,
        ];
        $users = User::query()->whereIn('id', [$customer->user_id, $seller->user_id])->get();
        return $this->sender->sendAllMessages($users, $data, $title, $body, 'rider_order_delivered');
    }


    public function ready(Order $order): mixed
    {
        throw new \Exception('Rider cannot mark order as ready');
    }

    /**
     * @throws \Exception
     */
    public function approved(Order $order, $exclude = []): mixed
    {
        return $this->sender->sendOrderApprovedNotification($order, $exclude);
    }
    public function pickup(Order $order): mixed
    {
        $rider = $order->rider;
        $customer = $order->customer;
        $title = 'Order Pick up';
        $body = 'Your Order ' . $order->reference . " has been picked up by $rider->full_name will is on the way";
        $data = [
            'user_id' => $customer->user_id,
            'order_id' => $order->id,
            'title' => $title,
            'body' => $body
        ];
        $this->sender->createNotification($data);
        $data['customer_id'] = $customer->id;
        return $this->sender->sendNotification($customer->user, $data, [
            'title' => $title,
            'body' => $body,
            'event' => 'customer_pick_up_order',
        ]);
    }

    public function arrived(Order $order, $place): mixed
    {
        $title = 'Rider Arrival';
        $rider = $order->rider;
        $customer = $order->customer;
        $seller = $order->seller;
        if ($place == 'seller') {
            $body = "$rider->full_name has arrived to pick up Order: $order->reference";
            $data = [
                'user_id' => $seller->user_id,
                'order_id' => $order->id,
                'title' => $title,
                'body' => $body
            ];

            return $this->sender->sendNotification($seller->user, $data, [
                'title' => $title,
                'body' => $body,
                'event' => 'river_arrived',
            ]);
        }
        if ($place == 'customer') {
            $body = "$rider->full_name has arrived with your Order: $order->reference";
            $data = [
                'user_id' => $customer->user_id,
                'order_id' => $order->id,
                'title' => $title,
                'body' => $body
            ];
            return $this->sender->sendNotification($customer->user, $data, [
                'title' => $title,
                'body' => $body,
                'event' => 'river_arrived',
            ]);
        }
    }

    public function delayed(Order $order, $time): mixed
    {
        return false;
    }
}