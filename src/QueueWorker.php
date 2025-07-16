<?php

namespace FastFast\Common;

use Illuminate\Console\OutputStyle;
class QueueWorker
{

    public function __construct(private array $workers)
    {
    }

    public function startWorker(OutputStyle $output = null): void
    {
        foreach ($this->workers as $worker) {
            if (method_exists($worker, 'setLogger')) {
                $worker->setLogger($output);
            }
            $worker->handle($output);
        }
    }
}
