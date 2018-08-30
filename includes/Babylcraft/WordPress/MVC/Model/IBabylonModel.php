<?php


namespace Babylcraft\WordPress\MVC\Model;

interface IBabylonModel 
{
    function setModelFactory(IModelFactory $modelFactory) : void;
    
    /**
     * Returns an array of all fields defined by this Model.
     * The array should be keyed by fieldname to facilitate faster lookups.
     * 
     * @return array
     */
    function getFields() : array;
    function hasField(string $fieldName) : bool;
    function setField(string $fieldName, $value) : void;
}
