<?php

namespace Pobl\Bongo;

use OdmCursor;

class Query implements \Iterator, \Countable
{
    /**
     *
     * @var \Pobl\Bongo\Client
     */
    private $client;
    
    /**
     *
     * @var \Pobl\Bongo\Collection
     */
    private $collection;
    
    /**
     *
     * @var string
     */
    private $modelClass;
    
    private $fields = array();
    
    /**
     *
     * @var \MongoCursor
     */
    private $cursor;
    
    private $skip = 0;
    
    /**
     *
     * @var \Pobl\Bongo\Expression
     */
    private $expression;
    
    private $limit = 0;
    
    private $sort = array();
    
    private $readPreferences = array();
    
    /**
     *
     * @var If specified in child class - overload config from collection class
     */
    protected $queryExpressionClass;
    
    /**
     *
     * @var boolean results are arrays instead of objects
     */
    private $options = array(
        'arrayResult'       => false,
        'expressionClass'   => '\Pobl\Bongo\Expression'
    );
    
    public function __construct(Collection $collection, $modelClass, array $options = null)
    {
        $this->collection = $collection;        
        $this->client = $this->collection->getDatabase()->getClient();
        $this->modelClass = $modelClass;
        
        if($options) {
            $this->options = array_merge($this->options, $options);
        }
        
        // expression
        $this->expression = $this->expression();
    }
    
    public function __call($name, $arguments) {
        call_user_func_array(array($this->expression, $name), $arguments);
        return $this;
    }
    
    /**
     * Return only specified fields
     * 
     * @param array $fields
     * @return \\Pobl\Bongo\Query
     */
    public function fields(array $fields)
    {
        $this->fields = array_fill_keys($fields, 1);
        return $this;
    }
    
    /**
     * Return all fields except specified
     * @param array $fields
     */
    public function skipFields(array $fields)
    {
        $this->fields = array_fill_keys($fields, 0);
        return $this;
    }
    
    /**
     * Append field to accept list
     * @param type $field
     * @return \\Pobl\Bongo\Query
     */
    public function field($field)
    {
        $this->fields[$field] = 1;
        return $this;
    }
    
    /**
     * Append field to skip list
     * 
     * @param type $field
     * @return \\Pobl\Bongo\Query
     */
    public function skipField($field)
    {
        $this->fields[$field] = 0;
        return $this;
    }
    
    public function slice($field, $limit, $skip = null)
    {
        $limit  = (int) $limit;
        $skip   = (int) $skip;
        
        if($skip) {
            if(!$limit) {
                throw new Exception('Limit must be specified');
            }
            
            $this->fields[$field] = array('$slice' => array($skip, $limit));
        }
        else {
            $this->fields[$field] = array('$slice' => $limit);
        }
        
        return $this;
    }
    
    public function query(Expression $expression)
    {
        $this->expression->merge($expression);
        return $this;
    }
    
    /**
     * 
     * @return \Pobl\Bongo\Expression
     */
    public function expression()
    {
        $expressionClass = $this->queryExpressionClass 
            ? $this->queryExpressionClass 
            : $this->options['expressionClass'];
        
        return new $expressionClass;
    }
    
    public function getExpression()
    {
        return $this->expression;
    }
    
    /**
     * get list of MongoId objects from array of strings, MongoId's and Document's
     * 
     * @param array $list
     * @return type
     */
    public function getIdList(array $list)
    {
        return array_map(function($element) {
            if($element instanceof \MongoId) {
                return $element;
            }
            
            if($element instanceof Document) {
                return $element->getId();
            }
            
            return new \MongoId($element);
        }, $list);
    }
    
    public function byIdList(array $idList)
    {
        $this->expression->whereIn('_id', $this->getIdList($idList));
        return $this;
    }
    
    public function byId($id)
    {
        if($id instanceof \MongoId) {
            $this->expression->where('_id', $id);
        } else {
            try {
                $this->expression->where('_id', new \MongoId($id));
            } catch (\MongoException $e) {
                $this->expression->where('_id', $id);
            }
        }

        return $this;
    }
    
    public function skip($skip)
    {
        $this->skip = (int) $skip;
        
        return $this;
    }
    
    public function limit($limit, $offset = null)
    {
        $this->limit = (int) $limit;
        
        if(null !== $offset) {
            $this->skip($offset);
        }
        
        return $this;
    }
    
    public function sort(array $sort)
    {
        $this->sort = $sort;
        
        return $this;
    }
    
    /**
     * 
     * @return \MongoCursor
     */
    public function getCursor()
    {
        if($this->cursor) {
            return $this->cursor;
        }
        
        $this->cursor = $this->collection
            ->getMongoCollection()
            ->find($this->expression->toArray(), $this->fields);
        
        
        if($this->skip) {
            $this->cursor->skip($this->skip);
        }
        
        if($this->limit) {
            $this->cursor->limit($this->limit);
        }
        
        if($this->sort) {
            $this->cursor->sort($this->sort);
        }
        
        // log request
        if($this->client->hasLogger()) {
            $this->client->getLogger()->debug(get_called_class() . ': ' . json_encode(array(
                'collection'    => $this->collection->getName(), 
                'query'         => $this->expression->toArray(),
                'project'       => $this->fields,
                'sort'          => $this->sort,
            )));
        }
        
        $this->cursor->rewind();
        
        // define read preferences
        if($this->readPreferences) {
            foreach($this->readPreferences as $readPreference => $tags) {
                $this->cursor->setReadPreference($readPreference, $tags);
            }
        }
        
        return $this->cursor;
    }
    
    /**
     * Count documents in result without applying limit and offset
     * @return int count
     */
    public function count()
    {
        return (int) $this->collection
            ->getMongoCollection()
            ->count($this->expression->toArray());
    }
    
    /**
     * Count documents in result with applying limit and offset
     * @return int count
     */
    public function limitedCount()
    {
        return (int) $this->collection
            ->getMongoCollection()
            ->count($this->expression->toArray(), $this->limit, $this->skip);
    }
    
    public function findOne()
    {
        $documentData = $this->collection
            ->getMongoCollection()
            ->findOne($this->expression->toArray(), $this->fields);
        
        if(!$documentData) {
            return null;
        }
        
        if($this->options['arrayResult']) {
            return $documentData;
        }
        
        $className = $this->collection->getDocumentClassName($documentData);
        
        return new $className($this->collection, $documentData, array(
            'stored' => true
        ));
    }
    
    /**
     * 
     * @return array result of searching
     */
    public function findAll()
    {
        return iterator_to_array($this);
    }
    
    public function findAndRemove()
    {
        $mongoDocument = $this->collection->getMongoCollection()->findAndModify(
            $this->expression->toArray(),
            null,
            $this->fields,
            array(
                'remove'    => true,
                'sort'      => $this->sort, 
            )
        );
        
        if(!$mongoDocument) {
            return null;
        }
        
        $documentClassName = $this->collection
            ->getDocumentClassName($mongoDocument);
        
        return new $documentClassName($this->collection, $mongoDocument, array(
            'stored' => true
        ));
    }
    
    public function findAndUpdate(Operator $operator, $upsert = false)
    {
        $mongoDocument = $this->collection->getMongoCollection()->findAndModify(
            $this->expression->toArray(),
            $operator ? $operator->getAll() : null,
            $this->fields,
            array(
                'new'       => true,
                'sort'      => $this->sort,
                'upsert'    => $upsert,
            )
        );
        
        if(!$mongoDocument) {
            return null;
        }
        
        $documentClassName = $this->collection
            ->getDocumentClassName($mongoDocument);
        
        return new $documentClassName($this->collection, $mongoDocument, array(
            'stored' => true
        ));
    }
    
    public function map($handler)
    {
        $result = array();
        
        foreach($this as $id => $document) {
            $result[$id] = $handler($document);
        }
        
        return $result;
    }
    
    public function filter($handler)
    {
        $result = array();
        
        foreach($this as $id => $document) {
            if(!$handler($document)) {
                continue;
            }
            
            $result[$id] = $document;
        }
        
        return $result;
    }
    
    public function findRandom()
    {
        $count = $this->count();
        
        if(!$count) {
            return null;
        }
        
        if(1 === $count) {
            return $this->findOne();
        }
        
        return $this
            ->skip(mt_rand(0, $count - 1))
            ->limit(1)
            ->current();
    }
    
    /**
     * 
     * @param type $page
     * @param type $itemsOnPage
     * @return \Pobl\Bongo\Paginator
     */
    public function paginate($page, $itemsOnPage = 30)
    {
        $paginator = new Paginator($this);
        
        return $paginator
            ->setCurrentPage($page)
            ->setItemsOnPage($itemsOnPage);
            
    }
    
    public function toArray()
    {
        return $this->expression->toArray();
    }
    
    public function current()
    {
        return $this->getCursor()->current();
        /*$documentData = $this->getCursor()->current();
        if(!$documentData) {
            return null;
        }
        
        if($this->options['arrayResult']) {
            return $documentData;
        }
        
        $className = $this->collection->getDocumentClassName($documentData);
        return new $className($this->collection, $documentData, array(
            'stored' => true
        ));*/
    }
    
    public function key()
    {
        return $this->getCursor()->key();
    }
    
    public function next()
    {
        $this->getCursor()->next();
        return $this;
    }
    
    public function rewind()
    {
        $this->getCursor()->rewind();
        return $this;
    }
    
    public function valid()
    {
        return $this->getCursor()->valid();
    }
    
    public function readPrimaryOnly()
    {
        $this->readPreferences[\MongoClient::RP_PRIMARY] = null;
        return $this;
    }
    
    public function readPrimaryPreferred(array $tags = null)
    {
        $this->readPreferences[\MongoClient::RP_PRIMARY_PREFERRED] = $tags;
        return $this;
    }
    
    public function readSecondaryOnly(array $tags = null)
    {
        $this->readPreferences[\MongoClient::RP_SECONDARY] = $tags;
        return $this;
    }
    
    public function readSecondaryPreferred(array $tags = null)
    {
        $this->readPreferences[\MongoClient::RP_SECONDARY_PREFERRED] = $tags;
        return $this;
    }
    
    public function readNearest(array $tags = null)
    {
        $this->readPreferences[\MongoClient::RP_NEAREST] = $tags;
        return $this;
    }
}
