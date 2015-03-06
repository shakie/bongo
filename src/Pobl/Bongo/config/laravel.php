<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Default MongoDb connection
     |--------------------------------------------------------------------------
     |
     */

    'default' => env('BONGO_DEFAULT_CONNECTION', 'default'),

    /*
     |
     | MongoDB connections
     |
     */

    'connections' => [
        'default' => [
            'host' => env('BONGO_HOST', 'localhost'),
            'port' => env('BONGO_PORT', 27017),
            'username' => env('BONGO_USERNAME', 'guest'),
            'password' => env('BONGO_PASSWORD', 'guest'),
            'database' => env('BONGO_DATABASE', 'guest')
        ]
    ]
];