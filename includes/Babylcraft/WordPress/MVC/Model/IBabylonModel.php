<?php


namespace Babylcraft\WordPress\MVC\Model;

use Babylcraft\WordPress\MVC\Model\Impl\UniqueModelIterator;


/**
 * TODO stop extending IMappedRecord here (requires a pretty major refactor because these
 * two interfaces are used interchangeably in some bits of code -- they used to be a single
 * interface).
 */
interface IBabylonModel extends IDataMapper
{
    /**
     * Load a Model from storage via F_ID
     * 
     * @throws ModelException ERR_RECORD_NOT_FOUND if data is not found for this model
     * using the value of F_ID, ERR_NO_ID if F_ID has not been changed from the default (-1)
     * @throws FieldException|PDOException
     */
    function hydrate() : void;
    
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
     * Sets the model's parent model. Will be validated as of the correct declared class
     * type according to the settings in F_PARENT.
     * 
     * TODO when / if the "big refactor" happens to a proper DataMapper pattern, this 
     * get/set pair would belong on the IDataMapper interface, but that would involve
     * passing in something other than an IBabylonModel as $parent (because IDataMapper
     * shouldn't know about that interface). So, it would be more like DECLARING the 
     * parent type (and tying that back the K_TYPE of the F_PARENT field etc).
     * The IDataMapper graph should basically be an in-memory representation of the data
     * schema, potentially defined by parsing XML / SQL definitions in a file. Definitely
     * TBD only when absolutely necessary!
     */
    function setParent(IBabylonModel $parent);

    /**
     * Return the model's ID (or -1 if not saved yet)
     */
    function getId();

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
     * Adds a child uniquely identified by $key to the list of children.
     * 
     * @param $key The key of the child object
     * @param $child The child object to add
     * 
     * @throws FieldException ERR_UNIQUE_VIOLATION if a child of that type already
     * exists at position $key
     */
    function addChildren(array $children) : void;

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
