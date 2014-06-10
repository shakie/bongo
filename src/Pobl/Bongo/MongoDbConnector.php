<?php

namespace Pobl\Bongo;

use \MongoClient;

class MongoDbConnector
{

    /**
     * The connection that will be used.
     *
     * @var MongoDB
     */
    public static $sharedConnection;

    /**
     * Returns the connection. If non existent then create it
     *
     * @var MongoDB
     */
    public function getConnection($connectionString = '')
    {
        // If exists in $shared_connection, use it
        if (MongoDbConnector::$sharedConnection) {
            $connection = MongoDbConnector::$sharedConnection;
        } else {
            // Else, connect and place connection in $shared_connection
            try {
                $connection = new MongoClient($connectionString);
            } catch (\MongoConnectionException $e) {
                trigger_error('Failed to connect with string: "' . $connectionString . '"');
            }

            MongoDbConnector::$sharedConnection = $connection;
        }

        return $connection;
    }

}
