<?php

namespace FastFast\Common\Publisher;

use Aws\Result;
interface PublisherInterface
{
    public function publish($data, $topic, $sub = null): Result|array;
    public function produce($data, $qUrl, $delay = 1, $param = []);
}