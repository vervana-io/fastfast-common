<?php

namespace Tests;

use FastFast\Common\FastFastCommonProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [FastFastCommonProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.env', 'testing');
        $app['config']->set('firebase.firestore.project_id', 'p');
        $app['config']->set('firebase.firestore.apiKey', 'k');
        $app['config']->set('firebase.firestore.database', '(default)');
        $app['config']->set('consumer.workers', []);
    }
}


