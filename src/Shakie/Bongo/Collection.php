<?php

namespace Shakie\Bongo;

class Collection implements \Countable
{

    /**
     *
     * @var string
     */
    protected $queryBuliderClass = '\Shakie\Bongo\QueryBuilder';

    /**
     *
     * @var string
     */
    protected $queryExpressionClass = '\Shakie\Bongo\Expression';

    /**
     *
     * @var \Shakie\Bongo\Database
     */
    private $database;

    /**
     *
     * @var \MongoCollection
     */
    private $mongoCollection;

    /**
     *
     * @var array
     */
    private $documentsPool = array();

    /**
     *
     * @var boolean
     */
    protected $documentPoolEnabled = false;

    public function __construct(Database $database, $collection)
    {
        $this->database = $database;

        if ($collection instanceof \MongoCollection) {
            $this->mongoCollection = $collection;
        } else {
            $this->mongoCollection = $database->getMongoDB()->selectCollection($collection);
        }
    }

    public function __get($name)
    {
        return $this->getDocument($name);
    }

    /**
     * Get name of collection
     * @return string name of collection
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
     * @return \Shakie\Bongo\Database
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
    public function getDocumentClassName(array $documentData = null)
    {
        return '\Shakie\Bongo\Model';
    }

    /**
     * 
     * @param array $data
     * @return \Shakie\Bongo\Document
     */
    public function createDocument(array $data = null)
    {
        $className = $this->getDocumentClassName($data);

        return new $className($this, $data, array(
            'stored' => false,
        ));
    }

    public function count()
    {
        return $this->find()->count();
    }

    /**
     * Create document query builder
     * 
     * @return \Shakie\Bongo\QueryBuilder|\Shakie\Bongo\Expression
     */
    public function find()
    {
        return new $this->_queryBuliderClass($this, array(
            'expressionClass' => $this->queryExpressionClass,
        ));
    }

    /**
     * Retrieve a list of distinct values for the given key across a collection.
     * 
     * @param string $selector field selector
     * @param \Shakie\Bongo\Expression $expression expression to search documents
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
     * @return \Shakie\Bongo\Expression
     */
    public function expression()
    {
        return new $this->_queryExpressionClass;
    }

    /**
     * 
     * @return \Shakie\Bongo\Operator
     */
    public function operator()
    {
        return new Operator;
    }

    /**
     * Create document query builder
     * 
     * @return \Shakie\Bongo\QueryBuilder
     */
    public function findAsArray()
    {
        return new $this->_queryBuliderClass($this, array(
            'expressionClass' => $this->queryExpressionClass,
            'arrayResult' => true
        ));
    }

    public function disableDocumentPool()
    {
        $this->documentPoolEnabled = false;
        return $this;
    }

    public function enableDocumentPool()
    {
        $this->documentPoolEnabled = true;
        return $this;
    }

    /**
     * Get document by id
     * 
     * @param string|MongoId $id
     * @return \Shakie\Bongo\Document|null
     */
    public function getDocument($id)
    {
        if (!$this->documentPoolEnabled) {
            return $this->getDocumentDirectly($id);
        }

        if (!isset($this->documentsPool[(string) $id])) {
            $this->documentsPool[(string) $id] = $this->getDocumentDirectly($id);
        }

        return $this->documentsPool[(string) $id];
    }

    /**
     * Get document by id directly omiting cache
     * 
     * @param type $id
     * @return \Shakie\Bongo\Document|null
     */
    public function getDocumentDirectly($id)
    {
        return $this->find()->byId($id)->findOne();
    }

    /**
     * Get document by id
     * 
     * @param string|MongoId $id
     * @return \Shakie\Bongo\Document|null
     */
    public function getDocuments(array $idList)
    {
        $documents = $this->find()->byIdList($idList)->findAll();
        if (!$documents) {
            return array();
        }

        if ($this->documentPoolEnabled) {
            $this->documentsPool = array_merge(
                    $this->documentsPool, $documents
            );
        }

        return $documents;
    }

    /**
     * 
     * @param \Shakie\Bongo\Document $document
     * @return \Shakie\Bongo\Collection
     * @throws \Exception
     * @throws \Shakie\Bongo\Document\Exception\Validate
     */
    public function saveDocument(Document $document, $validate = true)
    {
        $document->save($validate);
        return $this;
    }

    public function deleteDocument(Document $document)
    {
        $document->triggerEvent('beforeDelete');

        $status = $this->mongoCollection->remove(array(
            '_id' => $document->getId()
        ));

        $document->triggerEvent('afterDelete');

        if ($status['ok'] != 1) {
            throw new \Exception('Delete error: ' . $status['err']);
        }

        // drop from document's pool
        unset($this->documentsPool[(string) $document->getId()]);

        return $this;
    }

    public function deleteDocuments(Expression $expression)
    {
        $result = $this->mongoCollection->remove($expression->toArray());
        if (!$result) {
            throw new \Exception('Error removing documents from collection');
        }

        return $this;
    }

    public function insertMultiple($rows)
    {
        $document = $this->createDocument();

        foreach ($rows as $row) {
            $document->fromArray($row);

            if (!$document->isValid()) {
                throw new \Exception('Document invalid');
            }

            $document->reset();
        }

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
     * @return \Shakie\Bongo\AggregatePipelines
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
