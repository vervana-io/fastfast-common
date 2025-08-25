<?php

namespace FastFast\Common\Order;

use App\Jobs\OrderReminderJob;
use App\Models\Group_Order;
use App\Models\Rider;
use App\Models\Transaction;
use FastFast\Common\Notifications\NotificationSender;
use FastFast\Common\Service\FFOrderService;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class CustomerOrderService implements FFOrderService
{
    private NotificationSender $sender;
    public function __construct()
    {
        $this->sender = app(NotificationSender::class);
    }

    public function created( $order, $tranxn)
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
        $this->sender->sendNotification($seller->user, $not_data, [
            'title' => $title,
            'body' => $body,
            'event' => 'seller_new_order',
            'status' => 'created',
        ]);
    }

    public function verified(Order $order, $transId,)
    {
        $title = 'Order Verify';
        $body = 'Order ' . $order->reference . " has been verified";
        $seller = $order->seller;
        $data = [
            'user_id' => $seller->id,
            'order_id' => $order,
            'transaction_id' => $transId,
            'title' => $title,
            'body' => $body
        ];
        $this->sender->sendNotification($seller->user, $data, [
            'title' => $title,
            'body' => $body,
            'event' => 'verify_order',
            'status' => 'verified',
        ]);
    }


    public function canceled(Order $order, $transction, $reason)
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
        $not_data['seller_id'] = $seller->id;
        return $this->sender->sendNotification($seller->user, $not_data, [
            'title' => $title,
            'body' => $body,
            'event' => 'order_canceled',
            'status' => 'canceled',
        ]);

    }
    public function approved(Order $order, $exclude = [])
    {
        //TODO: handle seller approve other for customer
        Log::info('Order '. $order->id . 'approved', $order->toArray());
        return true;
    }



    public function ready(Order $order)
    {
        // TODO: Implement verified() method.
    }


    public function delivered(Order $order)
    {
        // TODO: Implement verified() method.
    }
    private function pickup(Order $order)
    {
        // TODO: Implement verified() method.
    }
    public function rejected(Order $order, Rider $rider)
    {
        // TODO: Implement verified() method.
    }

    private function arrived(Order $order, $place)
    {
        // TODO: Implement verified() method.
    }
}