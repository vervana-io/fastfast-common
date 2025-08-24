<?php

namespace FastFast\Common\Service;

use App\Models\Order;
use App\Models\Rider;
use App\Models\Group_Order;
interface FFOrderService
{

    public function created(Order $order, $tranxn);

    public function verified(Order $order, $transId);

    public function approved(Order $order);

    public function canceled(Order $order, $transId, $reason);

    public function rejected(Order $order, Rider $rider);

    public function delivered(Order $order);
}

