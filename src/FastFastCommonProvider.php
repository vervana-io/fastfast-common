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
            $conf = config('consumer.sqs');
            if (config('app.env') == 'local') {
                $conf['endpoint'] = env('AWS_ENDPOINT', 'http://fake-aws:8080');
            }
            return new \Aws\Sqs\SqsClient($conf);
        });

        $this->app->singleton(\Aws\Sns\SnsClient::class, function ($app) {
            $conf = config('consumer.sqs');
            if (config('app.env') == 'local') {
                $conf['endpoint'] = env('AWS_ENDPOINT', 'http://fake-aws:8080');
            }
            return new \Aws\Sns\SnsClient($conf);
        });

        $this->app->singleton(\FastFast\Common\Publisher\Publisher::class, function ($app) {
            return new \FastFast\Common\Publisher\Publisher($app->make(\Aws\Sns\SnsClient::class));
        });

        $this->app->singleton(\FastFast\Common\Consumer\Consumer::class, function ($app) {
            $consumer = new \FastFast\Common\Consumer\Consumer();
            //$consumer->logger = $app->make('log'); // inject Laravel logger
            return $consumer;
        });
    }
}