<?php


namespace Babylcraft\WordPress\MVC\Model;

interface IBabylonModel 
{
    //just replicating the possible return values from PHP's lame gettype() function in case they change in future
    const T_NULL   = 'NULL';
    const T_INT    = 'integer';
    const T_STRING = 'string';
    const T_BOOL   = 'boolean';
    const T_DATE   = \DateTime::class;
    const T_OBJECT = 'object';
    const T_ARRAY  = 'array';

    const K_NAME     = 'name';
    const K_TYPE     = 'type';
    const K_VALUE    = 'value';
    const K_MODE     = 'mode';
    const K_OPTIONAL = 'optional';

    const FIELD_ID     = -0x1;
    const FIELD_PARENT = -0x2;

    const FIELDS_DEFAULT = [
        //-1 indicates not created in DB yet
        self::FIELD_ID     => [ self::K_NAME  => 'id', self::K_TYPE => self::T_INT,          self::K_VALUE => -1, self::K_MODE => 'r' ],
        self::FIELD_PARENT => [                        self::K_TYPE => IBabylonModel::class, self::K_OPTIONAL => true                 ]
    ];

    function setModelFactory(IModelFactory $modelFactory) : void;

    /**
     * Load a Model from storage via FIELD_ID
     */
    public function loadRecord() : void;
    
    /**
     * Saves the instance to whatever storage mechanism was supplied to the
     * model factory given in ::setModelFactory(). Upon successful return
     * from this function, the model can be inspected to find any ID or other
     * unique tracking value provided by the persistence layer.
     * 
     * @throws ModelException|FieldException|PDOException
     */
    function save();

    /**
     * Returns an array of all field definitions defined by this Model.
     * The array is keyed by field to facilitate faster lookups.
     * 
     * @return array An array of full field definitions, including such things as
     * name, type, mode etc if they are specified for the field in question.
     */
    function getFields() : array;

    /**
     * Returns an array of all fields as a single-dimensional array in the form
     * [<field id> => $value]. Compare this with ::getFields(), which returns full
     * field definitions.
     * 
     * @return array An array of field key => value pairs
     */
    function getFieldsMap() : array;

    /**
     * Returns true or false depending on whether this Model has a definition
     * for the given field key.
     */
    function hasField(int $field) : bool;

    /**
     * Sets the field referred to by $field to the value contained in $value.
     * 
     * @param int $field The field key to store the value under.
     * @param mixed $value The value to set for the given field.
     * 
     * @throws FieldException::ERR_NOT_FOUND If the field is not defined on this Model
     * @throws FieldException::ERR_READ_ONLY If the field is not allowed to be set
     */
    function setValue(int $field, $value) : void;

    /**
     * Updates fields en-masse. 
     *
     * @param array $kvpairs Array of the form [<field key> => $value]
     * 
     * @throws FieldException::ERR_NOT_FOUND If a field is not defined on this Model
     * @throws FieldException::ERR_READ_ONLY If a field is not allowed to be set
     */
    function setValues(array $kvpairs) : void;

    /**
     * Get the value of a field.
     * 
     * @param int $field The key of the field to be looked up
     * 
     * @return mixed The value of the field
     */
    function getValue(int $field);
}
