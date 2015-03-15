<?php

namespace Pobl\Bongo;

use \Illuminate\Support\ServiceProvider;
use \Pobl\Bongo\Model;

class LaravelServiceProvider extends ServiceProvider
{

    public function boot()
    {
        //Publish the config
        $this->publishes([
            __DIR__ . '/config/laravel.php' => config_path('mongo.php')
        ]);

        Model::setDefaultConnection($this->app['mongodb']);
    }

    public function register()
    {
        //Merge config with default values
        $this->mergeConfigFrom(
                __DIR__ . '/config/laravel.php', 'mongo'
        );

        $this->app->singleton('mongodb', function($app) {
            $config = $app['config']['mongo']['connections'][$app['config']['mongo']['default']];
            $dsn = 'mongodb://' . ($config['username'] !== '' ? ($config['username']
                            . ':' . $config['password']) . '@' : '')
                    . $config['host'] . ':'
                    . $config['port'];
            $bongo = new Client($dsn);
            $bongo->useDatabase($config['database']);
            return $bongo;
        });
    }

}
