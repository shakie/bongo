<?php

namespace Pobl\Bongo;

use \Pobl\Bongo\Query;
use \Pobl\Bongo\Expression;

class Collection implements \Countable
{

    /**
     *
     * @var string
     */
    protected $queryClass = '\\Pobl\\Bongo\\Query';

    /**
     *
     * @var string
     */
    protected $queryExpressionClass = '\\Pobl\\Bongo\\Expression';

    /**
     *
     * @var string
     */
    protected $modelClass = '\\Pobl\\Bongo\\Model';

    /**
     *
     * @var \Pobl\Bongo\Database
     */
    private $database;

    /**
     *
     * @var \MongoCollection
     */
    private $mongoCollection;

    public function __construct(Database $database, $collection)
    {
        $this->database = $database;

        if ($collection instanceof \MongoCollection) {
            $this->mongoCollection = $collection;
        } else {
            $this->mongoCollection = $database->getMongoDB()->selectCollection($collection);
        }
    }

    /**
     * Get name of collection
     * @return string Name of collection
     */
    public function getName()
    {
        return $this->mongoCollection->getName();
    }

    /**
     *
     * @return MongoCollection
     */
    public function getMongoCollection()
    {
        return $this->mongoCollection;
    }

    /**
     *
     * @return \Pobl\Bongo\Database
     */
    public function getDatabase()
    {
        return $this->database;
    }

    public function delete()
    {
        $status = $this->mongoCollection->drop();
        if ($status['ok'] != 1) {
            // check if collection exists
            if ('ns not found' !== $status['errmsg']) {
                // collection exist
                throw new \Exception('Error deleting collection ' . $this->getName());
            }
        }

        return $this;
    }

    /**
     * Override to define classname of document by document data
     *
     * @param array $documentData
     * @return string Document class data
     */
    public function getModelClassName(array $documentData = null)
    {
        return $this->modelClass;
    }

    public function count()
    {
        return $this->mongoCollection->count();
    }

    /**
     * Create document query
     *
     * @return \Pobl\Bongo\Query|\Pobl\Bongo\Expression
     */
    public function getQuery($class)
    {
        return new $this->queryClass($this, $class, array(
            'expressionClass' => $this->queryExpressionClass,
        ));
    }

    /**
     * Retrieve a list of distinct values for the given key across a collection.
     *
     * @param string $selector field selector
     * @param \Pobl\Bongo\Expression $expression expression to search documents
     * @return array distinct values
     */
    public function getDistinct($selector, Expression $expression = null)
    {
        if ($expression) {
            $expression = $expression->toArray();
        }

        return $this->mongoCollection->distinct($selector, $expression);
    }

    /**
     *
     * @return \Pobl\Bongo\Expression
     */
    public function expression()
    {
        return new $this->queryExpressionClass();
    }

    /**
     *
     * @return \Pobl\Bongo\Model
     */
    public function createModel()
    {
        $modelClassName = $this->getModelClassName();
        return new $modelClassName();
    }

    public function insertMultiple($rows)
    {
        $result = $this->mongoCollection->batchInsert($rows);
        if (!$result || $result['ok'] != 1) {
            throw new \Exception('Batch insert error: ' . $result['err']);
        }

        return $this;
    }

    public function updateMultiple(Expression $expression, $updateData)
    {
        if ($updateData instanceof Operator) {
            $updateData = $updateData->getAll();
        }

        $status = $this->mongoCollection->update(
                $expression->toArray(), $updateData, array(
            'multiple' => true,
                )
        );

        if (1 != $status['ok']) {
            throw new \Exception('Multiple update error: ' . $status['err']);
        }

        return $this;
    }

    /**
     * Save data to Mongo
     *
     * @param array $data
     * @param type $options
     * @return array
     */
    public function save($data, $options)
    {
        return $this->mongoCollection->save($data, $options);
    }

    /**
     * Create Aggregator pipelines instance
     *
     * @return \Pobl\Bongo\AggregatePipelines
     */
    public function createPipeline()
    {
        return new AggregatePipelines($this);
    }

    /**
     * Aggregate using pipelines
     *
     * @param type $pipelines
     * @return array result of aggregation
     * @throws \Exception
     */
    public function aggregate($pipelines)
    {

        if ($pipelines instanceof AggregatePipelines) {
            $pipelines = $pipelines->toArray();
        } elseif (!is_array($pipelines)) {
            throw new \Exception('Wrong pipelines specified');
        }

        // log
        $client = $this->database->getClient();
        if ($client->hasLogger()) {
            $client->getLogger()->debug(get_called_class() . ': ' . json_encode($pipelines));
        }

        // aggregate
        $status = $this->mongoCollection->aggregate($pipelines);

        if ($status['ok'] != 1) {
            throw new \Exception($status['errmsg']);
        }

        return $status['result'];
    }

    public function validate($full = false)
    {
        $response = $this->mongoCollection->validate($full);
        if (!$response || $response['ok'] != 1) {
            throw new \Exception($response['errmsg']);
        }

        return $response;
    }

    public function ensureIndex($key, array $options = array())
    {
        $this->mongoCollection->ensureIndex($key, $options);
        return $this;
    }

    public function ensureUniqueIndex($key, $dropDups = false)
    {
        $this->mongoCollection->ensureIndex($key, array(
            'unique' => true,
            'dropDups' => (bool) $dropDups,
        ));

        return $this;
    }

    public function ensureSparseIndex($key)
    {
        $this->mongoCollection->ensureIndex($key, array(
            'sparse' => true,
        ));

        return $this;
    }

    public function readPrimaryOnly()
    {
        $this->mongoCollection->setReadPreference(\MongoClient::RP_PRIMARY);
        return $this;
    }

    public function readPrimaryPreferred(array $tags = null)
    {
        $this->mongoCollection->setReadPreference(\MongoClient::RP_PRIMARY_PREFERRED, $tags);
        return $this;
    }

    public function readSecondaryOnly(array $tags = null)
    {
        $this->mongoCollection->setReadPreference(\MongoClient::RP_SECONDARY, $tags);
        return $this;
    }

    public function readSecondaryPreferred(array $tags = null)
    {
        $this->mongoCollection->setReadPreference(\MongoClient::RP_SECONDARY_PREFERRED, $tags);
        return $this;
    }

    public function readNearest(array $tags = null)
    {
        $this->mongoCollection->setReadPreference(\MongoClient::RP_NEAREST, $tags);
        return $this;
    }

    /**
     * @param string|integer $w write concern
     * @param int $timeout timeout in miliseconds
     */
    public function setWriteConcern($w, $timeout = 10000)
    {
        if (!$this->mongoCollection->setWriteConcern($w, (int) $timeout)) {
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
        return $this->mongoCollection->getWriteConcern();
    }

    public function stats()
    {
        return $this->getDatabase()->executeCommand(array(
                    'collstats' => $this->getName(),
        ));
    }

}
