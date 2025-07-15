<?php

namespace FastFast\Common;

use Illuminate\Support\ServiceProvider;

class FastFastCommonProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/consumer.php' => config_path('consumer.php')
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \FastFast\Common\Console\StartWorker::class,
            ]);
        }
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\Aws\Sqs\SqsClient::class, function ($app) {
            return new \Aws\Sqs\SqsClient(config('consumer.sqs'));
        });

        $this->app->singleton(\Aws\Sns\SnsClient::class, function ($app) {
            return new \Aws\Sns\SnsClient(config('consumer.sns'));
        });

        $this->app->singleton(\FastFast\Common\Publisher\Publisher::class, function ($app) {
            return new \FastFast\Common\Publisher\Publisher($app->make(\Aws\Sns\SnsClient::class));
        });

        /*$this->app->singleton(\FastFast\Common\Consumer\Consumer::class, function ($app) {
            $checkForMessage = 2; // or get from config if needed
            $consumer = new \FastFast\Common\Consumer\Consumer($checkForMessage);
            //$consumer->logger = $app->make('log'); // inject Laravel logger
            return $consumer;
        });*/
    }
}