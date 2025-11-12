<?php

namespace FastFast\Common\Publisher;

use Aws\Result;
interface PublisherInterface
{
    public function publish($data, $topic, $sub = null): Result|array;
    public function produce($data, $qUrl, $paramAttributes = [], $id = 'order-message-group-id');
    public function produceBatch(array $entries, $qUrl);
}