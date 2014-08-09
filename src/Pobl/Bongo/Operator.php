<?php

namespace Pobl\Bongo;

class Operator
{
        /**
     *
     * @var array list of update operations
     */
    private $operators = array();
    
    public function set($fieldName, $value)
    {        
        if(!isset($this->operators['$set'])) {
            $this->operators['$set'] = array();
        }
        
        $this->operators['$set'][$fieldName] = $value;
        
        return $this;
    }
    
    public function push($fieldName, $value)
    {
        // no $push operator found
        if(!isset($this->operators['$push'])) {
            $this->operators['$push'] = array();
        }
        
        // no field name found
        if(!isset($this->operators['$push'][$fieldName])) {
            $this->operators['$push'][$fieldName] = $value;
        }
        
        // field name found and has single value
        else if(!is_array($this->operators['$push'][$fieldName]) || !isset($this->operators['$push'][$fieldName]['$each'])) {
            $oldValue = $this->operators['$push'][$fieldName];
            $this->operators['$push'][$fieldName] = array(
                '$each' => array($oldValue, $value)
            );
        }
        
        // field name found and already $each
        else {
            $this->operators['$push'][$fieldName]['$each'][] = $value;
        }
    }
    
    public function pushEach($fieldName, array $value)
    {
        // no $push operator found
        if(!isset($this->operators['$push'])) {
            $this->operators['$push'] = array();
        }
        
        // no field name found
        if(!isset($this->operators['$push'][$fieldName])) {
            $this->operators['$push'][$fieldName] = array(
                '$each' => $value
            );
        }
        
        // field name found and has single value
        else if(!isset($this->operators['$push'][$fieldName]['$each'])) {
            $oldValue = $this->operators['$push'][$fieldName];
            $this->operators['$push'][$fieldName] = array(
                '$each' => array_merge(array($oldValue), $value)
            );
        }
        
        // field name found and already $each
        else {
            $this->operators['$push'][$fieldName]['$each'] = array_merge(
                $this->operators['$push'][$fieldName]['$each'],
                $value
            );
        }
    }
    
    public function increment($fieldName, $value)
    {
        // check if update operations already added
        $oldIncrementValue = $this->get('$inc', $fieldName);
        if($oldIncrementValue) {
            $value = $oldIncrementValue + $value;
        }
        
        $this->operators['$inc'][$fieldName] = $value;
        
        return $this;
    }
    
    public function pull($fieldName, $value)
    {
        if($value instanceof Expression) {
            $value = $value->toArray();
        }
        
        // no $push operator found
        $this->operators['$pull'][$fieldName] = $value;
        
        return $this;
    }
    
    public function unsetField($fieldName)
    {
        $this->operators['$unset'][$fieldName] = '';
        return $this;
    }
    
    
    public function isDefined()
    {
        return (bool) $this->operators;
    }
    
    public function reset()
    {
        $this->operators = array();
        return $this;
    }
    
    public function get($operation, $fieldName = null)
    {
        if($fieldName) {
            return isset($this->operators[$operation][$fieldName])
                ? $this->operators[$operation][$fieldName]
                : null;
        }
        
        return isset($this->operators[$operation]) 
            ? $this->operators[$operation]
            : null;
    }
    
    public function getAll()
    {
        return $this->operators;
    }
    
    public function isReloadRequired()
    {
        return isset($this->operators['$inc']) || isset($this->operators['$pull']);
    }
}