<?php

namespace Pobl\Bongo;

class Paginator implements \Iterator
{
    private $currentPage = 1;
    
    private $itemsOnPage = 30;
    
    private $totalRowsCount;
    
    /**
     *
     * @var \Pobl\Bongo\Query
     */
    private $queryBuilder;
    
    public function __construct(QueryBuilder $queryBuilder = null)
    {
        if($queryBuilder) {
            $this->setQueryBuilder($queryBuilder);
        }
    }
    
    public function __destruct()
    {
        $this->queryBuilder = null;
    }
    
    /**
     * 
     * @param int $itemsOnPage
     * @return \Pobl\Bongo\Paginator
     */
    public function setItemsOnPage($itemsOnPage)
    {
        $this->itemsOnPage = (int) $itemsOnPage;
        
        $this->queryBuilder->limit($this->itemsOnPage);
        
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
        $this->queryBuilder = clone $queryBuilder;
        
        $this->applyLimits();
        
        return $this;
    }
    
    public function getTotalRowsCount()
    {
        if($this->totalRowsCount) {
            return $this->totalRowsCount;
        }
        
        $this->totalRowsCount = $this->queryBuilder->count();
        
        return $this->totalRowsCount;
    }
    
    public function getTotalPagesCount()
    {
        return (int) ceil($this->getTotalRowsCount() / $this->itemsOnPage);
    }
    
    private function applyLimits()
    {
        if(!$this->queryBuilder) {
            return;
        }
        
        $currentPage = $this->getCurrentPage();
        
        // get page of rows
        $this->queryBuilder
            ->limit($this->itemsOnPage)
            ->skip(($currentPage - 1) * $this->itemsOnPage);
    }
    
    /**
     * @return \Pobl\Bongo\Document
     */
    public function current()
    {
        return $this->queryBuilder->current();
    }
    
    public function key()
    {
        return $this->queryBuilder->key();
    }
    
    public function next()
    {
        $this->queryBuilder->next();
        return $this;
    }
    
    public function rewind()
    {
        $this->queryBuilder->rewind();
        return $this;
    }
    
    public function valid()
    {
        return $this->queryBuilder->valid();
    }
}