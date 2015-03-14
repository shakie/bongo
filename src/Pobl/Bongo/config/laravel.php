<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Default MongoDb connection
     |--------------------------------------------------------------------------
     |
     */

    'default' => env('MONGO_DEFAULT_CONNECTION', 'default'),

    /*
     |
     | MongoDB connections
     |
     */

    'connections' => [
        'default' => [
            'host' => env('MONGO_HOST', 'localhost'),
            'port' => env('MONGO_PORT', 27017),
            'username' => env('MONGO_USERNAME', 'guest'),
            'password' => env('MONGO_PASSWORD', 'guest'),
            'database' => env('MONGO_DATABASE', 'guest')
        ]
    ]
];