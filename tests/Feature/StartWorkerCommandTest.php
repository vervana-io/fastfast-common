<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StartWorkerCommandTest extends TestCase
{
    public function test_command_runs_all_workers()
    {
        $mockWorker = new class implements \FastFast\Common\Consumer\ConsumerInterface {
            public static $handled = false;
            public function handle() { self::$handled = true; }
        };

        Config::set('consumer.workers', [get_class($mockWorker)]);

        $this->app->bind(get_class($mockWorker), function () use ($mockWorker) {
            return $mockWorker;
        });

        Artisan::call('fastfast:start-worker');
        $this->assertTrue($mockWorker::$handled);
    }
} 