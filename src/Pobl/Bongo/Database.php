<?php

namespace Pobl\Bongo;

class Database
{

    /**
     *
     * @var \Pobl\Bongo\Client
     */
    private $client;

    /**
     *
     * @var string
     */
    private $databaseName;

    /**
     *
     * @var \MongoDB
     */
    private $mongoDB;

    /**
     *
     * @var array
     */
    private $mapping = array();

    /**
     *
     * @var string
     */
    private $classPrefix;

    /**
     *
     * @var array
     */
    private $collectionPool = array();

    public function __construct(Client $client, $databaseName)
    {
        $this->client = $client;
        $this->databaseName = $databaseName;

        $this->mongoDB = $this->client->getConnection()->selectDB($databaseName);
    }

    public function __get($name)
    {
        return $this->getCollection($name);
    }

    /**
     * 
     * @return \MongoDB
     */
    public function getMongoDB()
    {
        return $this->mongoDB;
    }

    /**
     * 
     * @return \Pobl\Bongo\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get class name mapped to collection
     * @param string $name name of collection
     * @return string name of class
     */
    public function getCollectionClassName($name)
    {
        $className = '\\Pobl\\Bongo\\Collection';

        return $className;
    }

    /**
     * Create collection
     * 
     * @param array $options array of options
     * @return \Pobl\Bongo\Collection
     */
    public function createCollection($name, array $options = null)
    {
        $className = $this->getCollectionClassName($name);
        if (!class_exists($className)) {
            throw new \Exception('Class ' . $className . ' not found while map collection name to class');
        }

        $mongoCollection = $this->getMongoDB()->createCollection($name, $options);
        return new $className($this, $mongoCollection);
    }

    /**
     * 
     * @param string $name name of collection
     * @param int $maxElements The maximum number of elements to store in the collection.
     * @param int $size Size in bytes.
     * @return \Pobl\Bongo\Collection
     * @throws \Exception
     */
    public function createCappedCollection($name, $maxElements, $size)
    {
        $options = array(
            'capped' => true,
            'size' => (int) $size,
            'max' => (int) $maxElements,
        );

        if (!$options['size'] && !$options['max']) {
            throw new \Exception('Size or number of elements must be defined');
        }

        return $this->createCollection($name, $options);
    }

    /**
     * 
     * @param string $name name of collection
     * @return \Pobl\Bongo\Collection
     */
    public function getCollection($name)
    {
        if (!isset($this->collectionPool[$name])) {
            $className = $this->getCollectionClassName($name);
            if (!class_exists($className)) {
                throw new \Exception('Class ' . $className . ' not found while map collection name to class');
            }

            $this->collectionPool[$name] = new $className($this, $name);
        }

        return $this->collectionPool[$name];
    }

    public function readPrimaryOnly()
    {
        $this->mongoDB->setReadPreference(\MongoClient::RP_PRIMARY);
        return $this;
    }

    public function readPrimaryPreferred(array $tags = null)
    {
        $this->mongoDB->setReadPreference(\MongoClient::RP_PRIMARY_PREFERRED, $tags);
        return $this;
    }

    public function readSecondaryOnly(array $tags = null)
    {
        $this->mongoDB->setReadPreference(\MongoClient::RP_SECONDARY, $tags);
        return $this;
    }

    public function readSecondaryPreferred(array $tags = null)
    {
        $this->mongoDB->setReadPreference(\MongoClient::RP_SECONDARY_PREFERRED, $tags);
        return $this;
    }

    public function readNearest(array $tags = null)
    {
        $this->mongoDB->setReadPreference(\MongoClient::RP_NEAREST, $tags);
        return $this;
    }

    /**
     * @param string|integer $w write concern
     * @param int $timeout timeout in miliseconds
     */
    public function setWriteConcern($w, $timeout = 10000)
    {
        if (!$this->mongoDB->setWriteConcern($w, (int) $timeout)) {
            throw new \Exception('Error setting write concern');
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
        return $this->mongoDB->getWriteConcern();
    }

    public function executeCommand(array $command, array $options = array())
    {
        return $this->getMongoDB()->command($command, $options);
    }

    public function stats()
    {
        return $this->executeCommand(array(
                    'dbstats' => 1,
        ));
    }

}
