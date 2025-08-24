<?php

namespace FastFast\Common\Order;

use FastFast\Common\Notifications\NotificationSender;

class OrderService
{
    protected NotificationSender $sender;
    public function __construct()
    {
        $this->sender = app(NotificationSender::class);
    }
}