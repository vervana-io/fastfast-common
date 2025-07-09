<?php

namespace Tests\Unit;

use FastFast\Common\QueueWorker;
use PHPUnit\Framework\TestCase;

class QueueWorkerTest extends TestCase
{
    public function test_start_worker_calls_handle_on_all_workers()
    {
        $worker1 = $this->createMock(\FastFastCommon\Consumer\ConsumerInterface::class);
        $worker2 = $this->createMock(\FastFastCommon\Consumer\ConsumerInterface::class);

        $worker1->expects($this->once())->method('handle');
        $worker2->expects($this->once())->method('handle');

        $queueWorker = new QueueWorker([$worker1, $worker2]);
        $queueWorker->startWorker();
    }
} 