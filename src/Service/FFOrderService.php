<?php

namespace FastFast\Common\Service;

use FastFast\Common\Service\Rider;
interface FFOrderService
{

    public function created(Order $order);

    public function verified(Order $order);

    public function approved(Order $order);

    public function canceled(Order $order);

    public function rejected(Order $order, Rider $rider);

    public function delivered(Order $order);
}

