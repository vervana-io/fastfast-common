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
                \FastFast\Common\Console\FirestoreTestCommand::class,
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
            return new \FastFast\Common\Publisher\Publisher($app->make(\Aws\Sns\SnsClient::class),$app->make(\Aws\Sqs\SqsClient::class));
        });

        $this->app->singleton(\FastFast\Common\Consumer\Consumer::class, function ($app) {
            $consumer = new \FastFast\Common\Consumer\Consumer();
            //$consumer->logger = $app->make('log'); // inject Laravel logger
            return $consumer;
        });

        $this->app->singleton(\FastFast\Common\Firestore\FirestoreClient::class, function ($app) {
            $projectId = config('firebase.firestore.project_id');
            $apiKey = config('firebase.firestore.apikey');
            $database = config('firebase.firestore.database', '(default)');
            
            if (empty($projectId) || empty($apiKey)) {
                throw new \InvalidArgumentException('Firebase Firestore configuration is missing. Please set firebase.firestore.project_id and firebase.firestore.apikey in your config.');
            }
            
            return new \FastFast\Common\Firestore\FirestoreClient($projectId, $apiKey, $database);
        });
    }
}