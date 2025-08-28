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

        return $this->sender->sendAllMessages([
            $seller,
            $customer,
        ], $data, $title, $body, 'rider_delivery_accept');
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
        return $this->approved($order, [$rider->id]);
    }

    public function delivered(Order $order): mixed
    {
        return false;
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
        return false;
    }

    public function arrived(Order $order, $place): mixed
    {

        return true;
    }

    public function delayed(Order $order, $time): mixed
    {
        return false;
    }
}