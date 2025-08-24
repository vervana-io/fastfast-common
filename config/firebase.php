<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Firebase services including
    | Firestore database access.
    |
    */

    'firestore' => [
        /*
        |--------------------------------------------------------------------------
        | Firebase Project ID
        |--------------------------------------------------------------------------
        |
        | The unique identifier for your Firebase project. You can find this
        | in your Firebase console under Project Settings > General.
        |
        */
        'project_id' => env('FIREBASE_PROJECT_ID'),

        /*
        |--------------------------------------------------------------------------
        | Firebase API Key
        |--------------------------------------------------------------------------
        |
        | The API key for your Firebase project. You can find this in your
        | Firebase console under Project Settings > General > Web API Key.
        |
        */
        'apikey' => env('FIREBASE_API_KEY'),

        /*
        |--------------------------------------------------------------------------
        | Firestore Database Name
        |--------------------------------------------------------------------------
        |
        | The name of your Firestore database. Defaults to '(default)' which
        | is the standard database name. You can specify a custom database
        | name if you're using multiple databases in your project.
        |
        */
        'database' => env('FIREBASE_DATABASE', '(default)'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Authentication (Optional)
    |--------------------------------------------------------------------------
    |
    | If you need Firebase Authentication, you can add these additional
    | configuration options.
    |
    */
    'auth' => [
        'domain' => env('FIREBASE_AUTH_DOMAIN'),
        'api_key' => env('FIREBASE_API_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],
];
