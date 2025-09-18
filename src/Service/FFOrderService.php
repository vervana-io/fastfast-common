<?php

namespace FastFast\Common\Service;

use App\Models\Order;
use App\Models\Rider;
use App\Models\Group_Order;
interface FFOrderService
{

    public function created(Order $order, $tranxn): mixed;

    public function verified(Order $order, $transId): mixed;

    public function approved(Order $order,  $exclude = [], $incrementDistance = false): mixed;

    public function canceled(Order $order, $transId, $reason): mixed;

    public function rejected(Order $order, Rider $rider): mixed;

    public function delivered(Order $order): mixed;
    public function ready(Order $order,  $exclude = [], $incrementDistance = false): mixed;
    public function delayed(Order $order, $time): mixed;
    public function accepted(Order $order): mixed;
    public function pickup(Order $order): mixed;
    public function arrived(Order $order, string $at): mixed;

}

