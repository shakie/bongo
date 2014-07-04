<?php

namespace Pobl\Bongo;

class Paginator implements \Iterator
{
    /**
     *
     * @var int
     */
    private $currentPage = 1;
    
    /**
     *
     * @var int
     */
    private $itemsOnPage = 30;
    
    /**
     *
     * @var int
     */
    private $totalRowsCount;
    
    /**
     *
     * @var \Pobl\Bongo\Query
     */
    private $query;
    
    public function __construct(Query $queryBuilder = null)
    {
        if($queryBuilder) {
            $this->setQueryBuilder($queryBuilder);
        }
    }
    
    public function __destruct()
    {
        $this->query = null;
    }
    
    /**
     * 
     * @param int $itemsOnPage
     * @return \Pobl\Bongo\Paginator
     */
    public function setItemsOnPage($itemsOnPage)
    {
        $this->itemsOnPage = (int) $itemsOnPage;
        $this->query->limit($this->itemsOnPage);
        
        // define offset
        $this->applyLimits();
        
        return $this;
    }
    
    /**
     * 
     * @param int $currentPage
     * @return \Pobl\Bongo\Paginator
     */
    public function setCurrentPage($currentPage)
    {        
        $this->currentPage = (int) $currentPage;
        
        // define offset
        $this->applyLimits();
        
        return $this;
    }
    
    public function getCurrentPage()
    {
        // check if current page number greater than max allowed
        $totalPageCount = $this->getTotalPagesCount();
        
        // no document found - page is 1
        if(!$totalPageCount) {
            return 1;
        }
        
        if($this->currentPage <= $totalPageCount) {
            $currentPage = $this->currentPage;
        } else {
            $currentPage = $totalPageCount;
        }
        
        return $currentPage;
    }
    
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->query = clone $queryBuilder;
        $this->applyLimits();
        
        return $this;
    }
    
    public function getTotalRowsCount()
    {
        if($this->totalRowsCount) {
            return $this->totalRowsCount;
        }
        
        $this->totalRowsCount = $this->query->count();
        
        return $this->totalRowsCount;
    }
    
    public function getTotalPagesCount()
    {
        return (int) ceil($this->getTotalRowsCount() / $this->itemsOnPage);
    }
    
    private function applyLimits()
    {
        if(!$this->query) {
            return;
        }
        
        $currentPage = $this->getCurrentPage();
        
        // get page of rows
        $this->query
            ->limit($this->itemsOnPage)
            ->skip(($currentPage - 1) * $this->itemsOnPage);
    }
    
    /**
     * @return \Pobl\Bongo\Model
     */
    public function current()
    {
        return $this->query->current();
    }
    
    public function key()
    {
        return $this->query->key();
    }
    
    public function next()
    {
        $this->query->next();
        return $this;
    }
    
    public function rewind()
    {
        $this->query->rewind();
        return $this;
    }
    
    public function valid()
    {
        return $this->query->valid();
    }
}