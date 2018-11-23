<?php

namespace Babylcraft\WordPress\MVC\Model;

interface IDataMapper
{
    /**
     * F_ constants are ints that define keys for fields that all IBabylonModel implementations share.
     * We use powers of 2 to facilitate bit-packing
     */
    const SHARED_KEY_INT_START = (PHP_INT_MAX -1) >> 0x10;
    const F_ID          = self::SHARED_KEY_INT_START,
          F_PARENT      = self::SHARED_KEY_INT_START + 0x2,
          F_CHILDREN    = self::SHARED_KEY_INT_START + 0x4,
          F_CHILD_TYPES = self::SHARED_KEY_INT_START + 0x8,
          F_TABLE_NAME  = self::SHARED_KEY_INT_START + 0x10;

    const DEFAULT_ID = -1; //what the ID starts at before an object has been saved

    /**
     * Here we make an array out of those shared fields and set their default properties.
     * Properties that do not have a value for IDataMapper::K_NAME will NOT be serialized or 
     * persisted.
     * 
     * @var array
     */
    const FIELDS_DEFAULT = [
        //-1 indicates not created in DB yet
        self::F_ID          => [ IDataMapper::K_NAME => 'id', IDataMapper::K_TYPE => IDataMapper::T_INT,    IDataMapper::K_VALUE    => self::DEFAULT_ID, IDataMapper::K_MODE => 'r' ],
        // you will need to set IDataMapper::K_REF_NAME => 'parentid' on the F_PARENT field of each interface if you want to query for all children at once
        self::F_PARENT      => [                                                                            IDataMapper::K_OPTIONAL => true                                  ],
        self::F_CHILDREN    => [                              IDataMapper::K_TYPE => IDataMapper::T_ARRAY,  IDataMapper::K_OPTIONAL => true                                  ],
        self::F_CHILD_TYPES => [                              IDataMapper::K_TYPE => IDataMapper::T_ARRAY,  IDataMapper::K_OPTIONAL => true                                  ],
        self::F_TABLE_NAME  => [                              IDataMapper::K_TYPE => IDataMapper::T_STRING, IDataMapper::K_OPTIONAL => true                                  ]
    ];

    function setModelFactory(IModelFactory $modelFactory) : void;

    /**
     * Use these datatypes when setting the allowed type of a field (mostly just replicating the possible return values from PHP's 
     * kinda lame gettype() function in case they change in future)
     */
    const T_NULL   = 'NULL',
          T_INT    = 'integer',
          T_STRING = 'string',
          T_BOOL   = 'boolean',
          T_DATE   = \DateTime::class,
          T_OBJECT = 'object',
          T_ARRAY  = 'array',
          T_MODEL_ITER = IUniqueModelIterator::class;

    /**
     * K_ constants are strings used to define keys into the `$fields[F_*]` array
     */
    const //accesses the persistence and/or serialization name of a given field - leave unset for transient properties
          K_NAME     = 'name',

          //accesses the data type of a given field
          K_TYPE     = 'type',

          //accesses the field's actual value
          K_VALUE    = 'value',

          //accesses the read / read-write mode of the field (all code assumes 'rw' unless set to 'r')
          K_MODE     = 'mode',

          //accesses whether or not the field is optional (all code assumes not unless set to true)
          K_OPTIONAL = 'optional',

          //accesses the persistence name of a field that refers to another object that contains this one (e.g a foreign-key)
          K_REF_NAME = 'ref_name';
          
    /**
     * Returns the persisted record name
     */
    function getRecordName() : string;

    /**
     * Returns an array of all field definitions defined by this Mapper.
     * The array is keyed by field to facilitate faster lookups.
     * 
     * @return array An array of full field definitions, including such things as
     * name, type, mode etc if they are specified for the field in question.
     */
    function getFields() : array;

    /**
     * Returns the type of the field identified by the given field code. This will be either
     * one of the T_* values in this interface, a fully-qualified class / interface name, or
     * null if not set.
     * 
     * @param int $field The numeric code for the field you wish to get the type for
     */
    function getFieldType(int $field) : ?string;

    /**
     * Returns the storage / serialization name of the field identified by the given field 
     * code, or null if not set.
     * 
     * @param int $field The numeric code for the field you wish to get the name for
     */
    function getFieldName(int $field) : ?string;

    /**
     * Returns a (possibly empty) array of all fields that have their field code OR-ed into
     * the given bit-packed int.
     */
    function getFieldNames(int $fieldPack) : array;

    /**
     * Returns the read/read-write mode of the field identified by the given field 
     * code.
     * 
     * @param int $field The numeric code for the field you wish to get the mode for
     */
    function getFieldMode(int $field) : ?string;

    /**
     * Returns true if the field identified by the given field code is optional, false
     * otherwise.
     * 
     * @param int $field The numeric code for the field you wish to check is optional
     */
    function isFieldOptional(int $field) : bool;

    /**
     * Returns an array of all field names mapped to values, optionally filtered by
     * $byFieldPack. Set $byFieldPack to zero for no filtering, otherwise create a bitwise
     * OR of the fields you wish returned.
     * 
     * Values are returned as a single-dimensional array in the form
     * [$fields[<field id>][<K_NAME>] => $value]. Compare this with ::getFields(), which 
     * returns full field definitions. Implementing classes may also filter the fields 
     * depending on whether or not the fields should be serialized when remoting to / from 
     * client-side apps.
     * 
     * If a field does not have a value for K_NAME, it will be filtered from the returned
     * results.
     * 
     * @return array An array of field key => value pairs
     */
    function getSerializable(int $byFieldPack) : array;

    /**
     * Given an array of the format returned by @see IDataMapper::getSerializable(), 
     * copies values from the array into this instance.
     * 
     * Readonly fields, or fields without a K_NAME will not have their values overwritten
     * (but will not throw an error).
     */
    function loadSerializeable(array $values) : void;

    /**
     * Returns an array of all field names that are updateable according to this model.
     */
    function getUpdateableNames() : array;

    /**
     * Returns true or false depending on whether this Model has a definition
     * for the given field key.
     */
    function hasField(int $field) : bool;
}