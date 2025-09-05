<?php

namespace Tests\Unit;

use FastFast\Common\QueueWorker;
use PHPUnit\Framework\TestCase;

class QueueWorkerTest extends TestCase
{
    public function test_start_worker_calls_handle_on_all_workers()
    {
        $worker1 = new class {
            public bool $handled = false;
            public function handle($output = null) { $this->handled = true; }
        };
        $worker2 = new class {
            public bool $handled = false;
            public function handle($output = null) { $this->handled = true; }
        };

        $queueWorker = new QueueWorker([$worker1, $worker2]);
        $queueWorker->startWorker();

        $this->assertTrue($worker1->handled);
        $this->assertTrue($worker2->handled);
    }
} 