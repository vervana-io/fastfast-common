<?php

namespace FastFast\Common\Order;

use Error;
use App\Jobs\OrderReminderJob;
use App\Models\Group_Order;
use App\Models\Rider;
use App\Models\Transaction;
use FastFast\Common\Notifications\NotificationSender;
use FastFast\Common\Service\FFOrderService;
use App\Models\Order;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;

class CustomerOrderService implements FFOrderService
{
    private NotificationSender $sender;
    public function __construct()
    {
        $this->sender = app(NotificationSender::class);
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function created($order, $tranxn): mixed
    {
        //send to seller and admin
        $title = 'New Order';
        $seller  = $order->seller;
        $user = $seller->user;
        $body = 'You have a new order: ' . $order->reference;
        if (!$tranxn) {
            $t = Transaction::where('order_id','=', $order->id)->first();
            $tranxn = $t->id;
        }
        $not_data = [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'transaction_id' => $tranxn,
            'title' => $title,
            'body' => $body,
            'screen' => 'order_details'
        ];
        $this->sender->createNotification($not_data);
        $not_data['seller_id'] = $seller->id;
        return $this->sender->sendNotification($seller->user, $not_data, [
            'title' => $title,
            'body' => $body,
            'event' => 'seller_new_order',
            'status' => 'created',
        ]);
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function verified(Order $order, $transId): mixed
    {
        $title = 'Order Verify';
        $body = 'Order ' . $order->reference . " has been verified";
        $seller = $order->seller;
        $data = [
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'transaction_id' => $transId,
            'title' => $title,
            'body' => $body
        ];
        $this->sender->createNotification($data);
        return $this->sender->sendNotification($seller->user, $data, [
            'title' => $title,
            'body' => $body,
            'event' => 'verify_order',
            'status' => 'verified',
        ]);
    }


    public function canceled(Order $order, $transction, $reason): mixed
    {
        //send to admin, seller
        $title = 'Order Cancellation';
        $body = 'Order ' . $order->reference . " has been cancelled, reason: " . $reason;
        $seller = $order->seller;
        $not_data = [
            'user_id' => $seller->user_id,
            'order_id' => $order->id,
            'transaction_id' => $transction,
            'title' => $title,
            'body' => $body
        ];
        $this->sender->createNotification($not_data);
        $not_data['seller_id'] = $seller->id;
        return $this->sender->sendNotification($seller->user, $not_data, [
            'title' => $title,
            'body' => $body,
            'event' => 'order_canceled',
            'status' => 'canceled',
        ]);

    }
    public function approved(Order $order, $exclude = [], $incrementDistance = false): mixed
    {
        //TODO: handle seller approve other for customer
        return false;
    }

    public function ready(Order $order, $exclude = [], $incrementDistance = false): mixed
    {
        return false;
    }


    public function delivered(Order $order): mixed
    {
        return false;
    }
    public function pickup(Order $order): mixed
    {
        throw new Error('Customer cannot pickup order');
    }
    public function rejected(Order $order, Rider $rider): mixed
    {

        return true;
    }

    public function arrived(Order $order, $place): mixed
    {
        throw new Error('Customer cannot pickup order');
    }

    public function delayed(Order $order, $time): mixed
    {
        throw new Error('Customer cannot delay order');
    }

    public function accepted(Order $order): mixed
    {
        throw new Error('Customer cannot pickup order');
    }
}