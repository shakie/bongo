<?php

namespace Shakie\Bongo;

class Client
{

    /**
     * MongoClient connection string
     *
     * @var string
     */
    private $dsn;

    /**
     * MongoClient connection options
     *
     * @var array
     */
    private $connectOptions = array('connect' => true);

    /**
     *
     * @var \MongoClient
     */
    private $connection;

    /**
     * Database pool
     *
     * @var array
     */
    private $databasePool = array();

    /**
     *
     * @var \Psr\LoggerInterface
     */
    private $logger;

    /**
     * Current database name
     *
     * @var string
     */
    private $currentDatabaseName;

    /**
     * @var array Database to class mapping
     */
    protected $mapping = array();

    /**
     * 
     * @param type $dsn
     * @param array $options
     */
    public function __construct($dsn = null, array $options = null)
    {
        if ($dsn) {
            $this->setDsn($dsn);
        }

        if ($options) {
            $this->setConnectOptions($options);
        }
    }

    public function __get($name)
    {
        return $this->getDatabase($name);
    }

    public function setDsn($dsn)
    {
        $this->dsn = $dsn;
        return $this;
    }

    public function setConnectOptions(array $options)
    {
        $this->connectOptions = $options;
        return $this;
    }

    public function setConnection(\MongoClient $client)
    {
        $this->connection = $client;
        return $this;
    }

    public function getConnection()
    {
        if (!$this->connection) {
            if (!$this->dsn) {
                throw new Exception('DSN not specified');
            }
            $this->connection = new \MongoClient($this->dsn, $this->connectOptions);
        }

        return $this->connection;
    }

    /**
     * Map database and collection name to class
     * 
     * @param array $name classpath or class prefix
     * Classpath:
     *  [dbname => [collectionName => collectionClass, ...], ...]
     * Class prefix:
     *  [dbname => classPrefix]
     * 
     * @return \Shakie\Bongo\Client
     */
    public function map(array $mapping)
    {
        $this->mapping = $mapping;

        return $this;
    }

    /**
     * 
     * @param string $name database name
     * @return \Shakie\Bongo\Database
     */
    public function getDatabase($name = null)
    {
        if (!$name) {
            $name = $this->getCurrentDatabaseName();
        }

        if (!isset($this->databasePool[$name])) {
            $this->databasePool[$name] = new Database($this, $name);
            if (isset($this->mapping[$name])) {
                $this->databasePool[$name]->map($this->mapping[$name]);
            }
        }

        return $this->databasePool[$name];
    }

    /**
     * Select database
     * 
     * @param string $databaseName
     * @return \Shakie\Bongo\Client
     */
    public function useDatabase($name)
    {
        $this->currentDatabaseName = $name;
        return $this;
    }

    public function getCurrentDatabaseName()
    {
        if (!$this->currentDatabaseName) {
            throw new Exception('Database not selected');
        }

        return $this->currentDatabaseName;
    }

    /**
     * Get collection from presiously seletced database by self::useDatabase()
     * 
     * @param string $name
     * @return \Shakie\Bongo\Collection
     * @throws Exception
     */
    public function getCollection($name)
    {
        return $this->getDatabase($this->getCurrentDatabaseName())
                        ->getCollection($name);
    }

    public function readPrimaryOnly()
    {
        $this->getConnection()->setReadPreference(\MongoClient::RP_PRIMARY);
        return $this;
    }

    public function readPrimaryPreferred(array $tags = null)
    {
        $this->getConnection()->setReadPreference(\MongoClient::RP_PRIMARY_PREFERRED, $tags);
        return $this;
    }

    public function readSecondaryOnly(array $tags = null)
    {
        $this->getConnection()->setReadPreference(\MongoClient::RP_SECONDARY, $tags);
        return $this;
    }

    public function readSecondaryPreferred(array $tags = null)
    {
        $this->getConnection()->setReadPreference(\MongoClient::RP_SECONDARY_PREFERRED, $tags);
        return $this;
    }

    public function readNearest(array $tags = null)
    {
        $this->getConnection()->setReadPreference(\MongoClient::RP_NEAREST, $tags);
        return $this;
    }

    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    public function hasLogger()
    {
        return (bool) $this->logger;
    }

    /**
     * @param string|integer $w wrint concern
     * @param int $timeout timeout in miliseconds
     */
    public function setWriteConcern($w, $timeout = 10000)
    {
        if (!$this->getConnection()->setWriteConcern($w, (int) $timeout)) {
            throw new Exception('Error setting write concern');
        }

        return $this;
    }

    /**
     * @param int $timeout timeout in miliseconds
     */
    public function setUnacknowledgedWriteConcern($timeout = 10000)
    {
        $this->setWriteConcern(0, (int) $timeout);
        return $this;
    }

    /**
     * @param int $timeout timeout in miliseconds
     */
    public function setMajorityWriteConcern($timeout = 10000)
    {
        $this->setWriteConcern('majority', (int) $timeout);
        return $this;
    }

    public function getWriteConcern()
    {
        return $this->getConnection()->getWriteConcern();
    }

}
