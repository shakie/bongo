<?php

namespace Pobl\Bongo;

class Laravel5Client extends Client
{

    /**
     * Overridden constructor for Laravel 5
     *
     * @param \Illuminate\Foundation\Application $app
     */
    public function __construct(\Illuminate\Foundation\Application $app)
    {
        //Construct the dsn from the config values
        $config = $app['config']['mongo']['connections'][$app['config']['mongo']['default']];
        $dsn = 'mongodb://' . ($config['username'] !== '' ? ($config['username']
                        . ':' . $config['password']) . '@' : '')
                . $config['host'] . ':'
                . $config['port'];
        $options = isset($config['options']) ? $config['options'] : null;
        parent::__construct($dsn, $options);
        //Set the database if value exists in config
        if (isset($config['database'])) {
            $this->useDatabase($config['database']);
        }
    }

}
