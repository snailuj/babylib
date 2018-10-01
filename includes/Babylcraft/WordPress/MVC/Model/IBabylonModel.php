<?php


namespace Babylcraft\WordPress\MVC\Model;

use Babylcraft\WordPress\MVC\Model\Impl\UniqueModelIterator;


interface IBabylonModel 
{
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
    const K_NAME     = 'name', //accesses the persistence and/or serialization name of a given field - leave unset for transient properties
          K_TYPE     = 'type', //accesses the data type of a given field
          K_VALUE    = 'value', //accesses the field's actual value
          K_MODE     = 'mode', //accesses the read / read-write mode of the field (all code assumes 'rw' unless set to 'r')
          K_OPTIONAL = 'optional'; //accesses whether or not the field is optional (all code assumes not unless set to true)

    /**
     * F_ constants are ints that define keys for fields that all IBabylonModel implementations share
     */
    const F_ID          = -0x1,
          F_PARENT      = -0x2,
          F_CHILDREN    = -0x3,
          F_CHILD_TYPES = -0x4,
          F_TABLE_NAME  = -0x5;


    const DEFAULT_ID = -1;

    /**
     * Here we make an array out of those shared fields and set their default properties.
     * Properties that do not have a value for self::K_NAME will NOT be serialized or 
     * persisted.
     * 
     * @var array
     */
    const FIELDS_DEFAULT = [
        //-1 indicates not created in DB yet
        self::F_ID          => [ self::K_NAME => 'id', self::K_TYPE => self::T_INT,    self::K_VALUE    => self::DEFAULT_ID, self::K_MODE => 'r' ],
        self::F_PARENT      => [                                                       self::K_OPTIONAL => true                                  ],
        self::F_CHILDREN    => [                       self::K_TYPE => self::T_ARRAY,  self::K_OPTIONAL => true                                  ],
        self::F_CHILD_TYPES => [                       self::K_TYPE => self::T_ARRAY,  self::K_OPTIONAL => true                                  ],
        self::F_TABLE_NAME  => [                       self::K_TYPE => self::T_STRING, self::K_OPTIONAL => true                                  ]
    ];

    function setModelFactory(IModelFactory $modelFactory) : void;

    /**
     * Load a Model from storage via F_ID
     * 
     * @throws ModelException ERR_RECORD_NOT_FOUND if data is not found for this model
     * using the value of F_ID, ERR_NO_ID if F_ID has not been changed from
     * the default (-1)
     */
    function loadRecord() : void;
    
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
     * Return the model's parent model, null if there is none.
     */
    function getParent();

    /**
     * Return the model's ID (or -1 if not saved yet)
     */
    function getId();

    /**
     * Returns an array of all field definitions defined by this Model.
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
     * Returns an array of all fields as a single-dimensional array in the form
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
    function getSerializable() : array;

    /**
     * Returns true or false depending on whether this Model has a definition
     * for the given field key.
     */
    function hasField(int $field) : bool;

    /**
     * Returns an array of fully-qualified interface names that represent child Model types
     * of this Model. Empty array if none.
     */
    function getChildTypes() : array;

    /**
     * Returns true if this Model currently contains children that implement the given
     * interface, or false otherwise
     * 
     * @param $interface The fully-qualified name (interface including namespace) of the
     * child type to check for
     * 
     * @return bool
     */
    function hasChildren(string $interface) : bool;

    /**
     * Returns the child object that matches the given key and implements the interface given 
     * by the fully-qualified name $interface, or null if not found.
     * 
     * @param $key The key of the child object
     * @param $interface The fully-qualified name (interface including namespace) of the type
     * to search for
     * 
     * @return IBabylonModel|null The model that implements $interface at position $key or null if
     * not found
     */
    function getChild($key, string $interface) : ?IBabylonModel;

    /**
     * Adds a child uniquely identified by $key to the list of children.
     * 
     * @param $key The key of the child object
     * @param $child The child object to add
     * 
     * @throws FieldException ERR_UNIQUE_VIOLATION if a child of that type already
     * exists at position $key
     */
    function addChild($key, IBabylonModel $child) : void;

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
