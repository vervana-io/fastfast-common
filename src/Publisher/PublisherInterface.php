<?php

namespace Fastfast\Common\Publisher;

use Aws\Result;
interface PublisherInterface
{
    public function publish($data, $topic, $sub = null): Result;
}