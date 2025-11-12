<?php

namespace FastFast\Common;

use FastFast\Common\Firestore\FirestoreClient;
use FastFast\Common\Notifications\APNotification;
use FastFast\Common\Notifications\FirebaseNotification;
use FastFast\Common\Notifications\NotificationSender;
use FastFast\Common\Notifications\PusherNotification;
use FastFast\Common\Publisher\Publisher;
use FastFast\Common\Publisher\PublisherInterface;
use Aws\Sqs\SqsClient;
use Aws\Sns\SnsClient;
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

        $this->app->singleton(SqsClient::class, function ($app) {
            $conf = config('consumer.sqs');
            if (config('app.env') == 'local') {
                $conf['endpoint'] = env('AWS_ENDPOINT', 'http://localstack:4566');
            }
            return new SqsClient($conf);
        });

        $this->app->singleton(SnsClient::class, function ($app) {
            $conf = config('consumer.sns');
            if (config('app.env') == 'local') {
                $conf['endpoint'] = env('AWS_ENDPOINT', 'http://localstack:4566');
            }
            return new SnsClient($conf);
        });

        $this->app->singleton(Publisher::class, function ($app) {
            return new Publisher($app->make(SnsClient::class), $app->make(SqsClient::class));
        });

        $this->app->bind(PublisherInterface::class, Publisher::class);


        $this->app->singleton(\FastFast\Common\Firestore\FirestoreClient::class, function ($app) {
            $projectId = config('firebase.firestore.project_id');
            $apiKey = config('firebase.firestore.apiKey');
            $database = config('firebase.firestore.database', '(default)');
            
            if (empty($projectId) || empty($apiKey)) {
                throw new \InvalidArgumentException('Firebase Firestore configuration is missing. Please set firebase.firestore.project_id and firebase.firestore.apikey in your config.');
            }
            
            return new \FastFast\Common\Firestore\FirestoreClient($projectId, $apiKey, $database);
        });
        $this->app->singleton(NotificationSender::class, function ($app) {
            return new NotificationSender(
                $app->make(FirestoreClient::class),
                $app->make(PusherNotification::class),
                $app->make(FirebaseNotification::class),
                $app->make(APNotification::class),
                $app->make(PublisherInterface::class)
            );
        });
    }
}