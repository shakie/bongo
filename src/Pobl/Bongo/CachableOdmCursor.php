<?php

namespace Pobl\Bongo;

class CachableOdmCursor implements \Iterator
{

    /**
     * The Query that generated this cursor
     *
     * @var \Pobl\Bongo\Expression
     */
    protected $query;

    /**
     * Documents returned from query
     *
     * @var array containing instances of model
     */
    protected $documents;
    
    protected $position = 0;
    
    protected $modelClass;

    /**
     * OdmCursor constructor. The query and the
     * model should be provided
     *
     * @param $query Array (The query that "Generated" this cursor )
     * @param $model string
     * @return void
     */
    public function __construct(Expression $query, $model)
    {
        $this->query = $query;
        $this->modelClass = $model;
        $this->documents = $this->getOdmCursor()->toArray(false);
    }

    /**
     * Calls the MongoCursor method if it exists.
     * This makes possible to run methods like limit, skip,
     * orts, and others.
     *
     * @param $name string
     * @param $args array
     */
    public function __call($name, $args)
    {
        return call_user_func_array(array($this->getOdmCursor(), $name), $args);
    }

    /**
     * Returns the OdmCursor object that are going to be
     * generated by the $this->query
     *
     * @return OdmCursor
     */
    public function getOdmCursor()
    {
        /* @var $model \Pobl\Bongo\Model */
        $model = $this->modelClass;
        $OdmCursor = $model::where($model::query()->query($this->query));

        if ($this->position > 0) {
            $OdmCursor->skip($this->position);
        }

        return $OdmCursor;
    }

    /**
     * Returns the MongoCursor object
     *
     * @return \MongoCursor
     */
    public function getCursor()
    {
        return $this->getOdmCursor()->getCursor();
    }

    /**
     * Iterator interface rewind (used in foreach)
     *
     */
    function rewind()
    {
        $this->position = 0;
    }

    /**
     * Iterator interface current. Return a model object
     * with cursor document. (used in foreach)
     *
     * @return mixed
     */
    function current()
    {
        return $this->documents[$this->position];
    }

    /**
     * Convert the cursor instance to an array.
     *
     * @return array
     */
    public function toArray($documentsToArray = true, $limit = false)
    {
        if ($documentsToArray) {
            $result = array();

            foreach ($this as $document) {
                $result[] = $document->getAttributes();
            }
        } else {
            $result = $this->documents;
        }

        return $result;
    }

    /**
     * Returns the first element of the cursor
     *
     * @return mixed
     */
    public function first()
    {
        return $this->documents[0];
    }

    /**
     * Iterator key method (used in foreach)
     *
     */
    function key()
    {
        return $this->position;
    }

    /**
     * Iterator next method (used in foreach)
     *
     */
    function next()
    {
        ++$this->position;
    }

    /**
     * Iterator valid method (used in foreach)
     *
     */
    function valid()
    {
        return isset($this->documents[$this->position]);
    }

    function sort($fields)
    {
        return $this->getOdmCursor()->sort($fields);
    }

    /**
     * Convert the cursor to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Convert the cursor to its JSON representation.
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        $result = array();

        foreach ($this->documents as $document) {
            $result[] = $document->toJson($options);
        }

        $result = '[' . implode($result, ',') . ']';

        return $result;
    }

}
