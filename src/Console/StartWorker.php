<?php

namespace FastFast\Common\Console;

use Illuminate\Console\Command;

class StartWorker extends Command
{
    protected $signature = 'fastfast:start-worker';
    protected $description = 'Start all FastFast queue workers';

    public function handle()
    {
        $workerClasses = config('consumer.workers', []);
        if (empty($workerClasses)) {
            $this->error('No workers defined in consumer.workers config.');
            return 1;
        }

        $this->info('Starting workers:');
        $workers = [];
        foreach ($workerClasses as $workerClass) {
            $this->info("- $workerClass");
            $workers[] = app($workerClass); // Use Laravel container for DI
        }

        $queueWorker = new \FastFast\Common\QueueWorker($workers);
        $queueWorker->startWorker();
        $this->info('All workers finished.');
        return 0;
    }
}