<?php

namespace FastFast\Common\Order;

use FastFast\Common\Service\FFOrderService;
use App\Models\Order;
use App\Models\Rider;

class SellerOrderService implements FFOrderService
{

    public function created(Order $order, $tranxn)
    {
        // TODO: Implement created() method.
    }

    public function verified(Order $order, $transId)
    {
        // TODO: Implement verified() method.
    }

    public function approved(Order $order)
    {
        // TODO: Implement approved() method.
    }

    public function canceled(Order $order, $transId, $reason)
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
            //$this->send_user_order_cancel_email($user, $order);
            //return $this->sender->sendNotification($user, $not_data, $title, $body, 'order_canceled', 'canceled');

    }

    public function rejected(Order $order, Rider $rider)
    {
        // TODO: Implement rejected() method.
    }

    public function delivered(Order $order)
    {
        // TODO: Implement delivered() method.
    }
}