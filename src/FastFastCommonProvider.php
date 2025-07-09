<?php

namespace Fastfast\Common;

use Fastfast\Common\Consumer\ConsumerInterface;
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


    }

    /**
     * @return void
     */
    public function register()
    {

    }
}