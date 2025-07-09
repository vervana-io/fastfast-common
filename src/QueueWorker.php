<?php

namespace FastFast\Common;

class QueueWorker
{

    public function __construct(private array $workers)
    {
    }

    public function startWorker(): void
    {
       foreach ($this->workers as $worker) {
           $worker->handle();
       }
    }
}
