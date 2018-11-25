<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IModelFactory;
use Babylcraft\WordPress\MVC\Model\ModelException;

use Babylcraft\WordPress\MVC\Model\HasPersistence;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;


/**
 * TODO catch PDO exceptions and wrap / transfer them into ModelException (or whatever)
 */
abstract class BabylonModel implements IBabylonModel
{    
    protected $pdo;
    protected $wpdb;
    protected $modelFactory;

    protected $fields = [];
    protected $dirty = false;
    protected $tableNamespace = '';

    #region Factory and lifecycle stuff (not IBabylonModel-defined)
    protected function __construct() //can only be built from within via static methods
    {
        $this->setupFields();
        $this->setDirty(true); //if created from code, needs DB save
    }

    abstract static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false
    ) : string;

    public function configureDB(
        \PDO $pdo,
        \wpdb $wpdb,
        string $tableNamespace = 'babyl_',
        string $wpTableNamespace = 'wp_'
    ) {
        $this->pdo = $pdo;
        $this->wpdb = $wpdb;
        $this->tableNamespace = $tableNamespace;
    }
    #endregion

    #region IBabylonModel implementation
    /**
     * Load from storage via F_ID
     * @see IBabylonModel::loadRecord()
     */
    public function hydrate() : void
    {
        $this->doHydrate();
        $this->setDirty(false, $recurse = true);
    }

    /**
     * @see IDataMapper::save()
     */
    public function save(bool $recurse = true) : void
    {
        if ( $this->isDirty() ) {
            $this->allValid();
            $this->getModelFactory()->persist($this);
            $this->setDirty(false);
        }

        if ( $recurse ) {
            $this->saveChildren($recurse);
        }
    }

    /**
     * @see IBabylonModel::getId()
     */
    public function getId()
    {
        return $this->getValue(static::F_ID);
    }

    /**
     * @see IBabylonModel::getValue()
     */
    public function getValue(int $field)
    {
        return $this->isNull($val = $this->doGetValue($field)) ? null : $val;
    }

    public function setParent(IBabylonModel $parent) : void
    {
        $this->setValue(static::F_PARENT, $parent);
    }

    public function getParent()
    {
        return $this->getValue(static::F_PARENT) ?? null;
    }

    public function setModelFactory(IModelFactory $modelFactory) : void
    {
        $this->modelFactory = $modelFactory;
    }

    /**
     * @see IBabylonModel::getChildTypes()
     */
    public function getChildTypes() : array
    {
        return $this->getValue(static::F_CHILD_TYPES);
    }

    /**
     * @see IBabylonModel::hasChildren()
     */
    public function hasChildren(string $interface) : bool
    {
        return null !== $this->getChildIterator($interface);
    }

    /**
     * @see IBabylonModel::getChild()
     */
    public function getChild($key, string $interface) : ?IBabylonModel
    {   //get the UniqueModelIterator for children having the class that implements the given interface
        return $this->getChildIterator($interface)[$key] ?? null;
    }

    /**
     * @see IBabylonModel::addChild()
     */
    public function addChild($key, IBabylonModel $child) : IBabylonModel
    {
        if (!$iter = $this->getChildIterator($this->getModelFactory()->getModelInterface($child))) {
            throw new FieldException(FieldException::ERR_NOT_FOUND, "Child is not of a declared child type for this model. ");
        }

        $child->setParent($this);
        return $iter[$key] = $child;
    }

    public function addChildren(array $children) : void
    {
        foreach ( $children as $child ) {
            $this->addChild($this->getChildKey($child), $child);
        }
    }

    protected function getChildKey(IBabylonModel $child)
    {
        return $child->getId();
    }

    /**
     * @see IBabylonModel::setValues()
     */
    public function setValues(array $kvpairs) : void
    {   //TODO perftest this
        foreach ( $kvpairs as $key => $val ) {
            $this->setValue($key, $val);
        }
    }

    /**
     * @see IBabylonModel::setValue()
     */
    public function setValue(int $field, $value) : void
    {
        //TODO optimise here by only setting the dirty flag if the value is actually different
        //requires equality comparisons but will prevent unnecessary DB updates

        //validate against all validatable errors, throws exception if invalid
        $this->validate($field, $value);
        $this->doSetValue($field, $value);
        $this->setDirty(true);
    }
    #endregion

    #region IDataMapper Implementation
    public function getFieldType(int $field) : ?string
    {
        if ($field = $this->fields[$field] ?? null) {
            return $field[static::K_TYPE] ?? null;
        }

        throw new FieldException(FieldException::ERR_NOT_FOUND, "given field $field");
    }

    public function getFieldName(int $field) : ?string
    {
        if ($field = $this->fields[$field] ?? null) {
            return $field[static::K_NAME] ?? null;
        }

        throw new FieldException(FieldException::ERR_NOT_FOUND, "given field $field");
    }

    public function getFieldNames(int $fieldPack) : array
    {
        $names = [];
        foreach ($this->fields as $code => $defn ) {
            if ($code & $fieldPack !== 0) {
                $names[] = $defn[static::K_NAME];
            }
        }

        return $names;
    }

    public function getFieldMode(int $field) : string
    {
        if ($field = $this->fields[$field] ?? null) {
            return $field[static::K_MODE] ?? 'rw';
        }

        throw new FieldException(FieldException::ERR_NOT_FOUND, "given field $field");
    }

    public function isFieldOptional(int $field) : bool
    {
        if ($field = $this->fields[$field] ?? null) {
            return ($optional = $field[static::K_OPTIONAL] ?? false) && $optional;
        }

        throw new FieldException(FieldException::ERR_NOT_FOUND, "given field $field");
    }

    public function getRecordName(): string
    {
        return $this->getValue(IDataMapper::F_TABLE_NAME);
    }

    /**
     * @see IDataMapper::getFields()
     */
    public function getFields() : array
    {
        return $this->fields;
    }

    /**
     * @see IDataMapper::hasField()
     */
    public function hasField(int $field) : bool
    {
        return array_key_exists($field, $this->getFields());
    }

    /**
     * @see IDataMapper::getSerializable()
     */
    public function getSerializable(int $byFieldPack = 0) : array
    {   //override in subclasses if you need to trim / augment values for serialization
        $map = [];
        foreach( $this->fields as $field => $fieldDef ) {
            if ( 
                //include a value only if K_NAME is set (if not set it's a transient prop)
                isset($fieldDef[static::K_NAME]) &&
                //F_PARENT has special handling below this loop, so ignore in here
                $field != static::F_PARENT &&
                //and if the field's code is present in the bit-pack
                ($byFieldPack == 0 || ($field & $byFieldPack !== 0))
            ) {
                //\Babylcraft\WordPress\PluginAPI::debug("getSerializable() : field number: ". $field ." has name ". $fieldDef[static::K_NAME]);
                $map[$fieldDef[static::K_NAME]] = $this->getValue($field);
            }
        }

        //extract parent id from the parent object if one is defined
        if (null != ($parentFieldName = $model->getFieldName(IDataMapper::F_PARENT))) {
            if (null == $model->getParent()) {
                throw new FieldException(FieldException::ERR_IS_NULL, "parent is required when serializing ". get_class($model));
            }

            $map[$parentFieldName] = $this->getParent()->getId();
        }

        return $map;
    }

    /**
     * @see IDataMapper::loadSerializeable()
     */
    public function loadSerializeable(array $values) : void
    {
        $patch = [];
        foreach ( $this->fields as $field => $fieldDef ) {
            if ( isset($fieldDef[static::K_NAME]) && //if this field has a K_NAME
                //and it's not F_PARENT (not handled by a simple deserialize)
                $field != static::F_PARENT &&
                //and is not read-only
                (!isset($fieldDef[static::K_MODE]) || $fieldDef[static::K_MODE] == 'r') &&
                //and there is a value in $values with that key
                 isset($values[$fieldDef[static::K_NAME]])
            ) { //then add to the patch
                $patch[$fieldDef[static::K_NAME]] = $values[$fieldDef[static::K_NAME]];
            }
        }

        $this->setValues($patch);
    }

    public function getUpdateableNames() : array
    {
        $idName = $this->getFieldName(static::F_ID);
        return array_filter(
            $this->fields,
            function($field) {
                return (null != $field[IDataMapper::K_NAME] ?? null)
                    && $field[IDataMapper::K_NAME] !== $idName;
            }
        );
    }
    #endregion

    #region protected
    protected function setupFields() : void
    {
        $this->addFields(self::FIELDS_DEFAULT);
    }

    protected function doHydrate() : void
    {
        //do default load process
        $this->getModelFactory()->hydrate($this);
    }

    /**
     * Override if you need special logic to determine saving behaviour
     */
    public function isDirty() : bool
    {
        return $this->dirty;
    }

    protected function setDirty(bool $dirty, $recurse = false) : void
    {
        $this->dirty = $dirty;
        if ($recurse) {
            foreach ( $this->getChildIterators() as $iter ) {
                foreach ( $iter as $key => $model ) {
                    $model->setDirty($dirty, $recurse);
                }
            }
        }
    }

    /**
     * Like ::setValues() but assumes the given array is indexed by K_NAME and not
     * F_* values.
     */
    protected function loadValues(array $nameValuePairs) : void
    {
        foreach ( $this->fields as $code => $defn ) {
            if ($val = $nameValuePairs[$defn[static::K_NAME] ?? null] ?? null) {
                $this->setValue($code, $val);
            }
        }
    }

    protected function saveChildren($recurse = true) : void
    {
        foreach ( $this->getChildIterators() as $iter ) {
            foreach ( $iter as $key => $model ) {
                $model->save($recurse);
            }
        }
    }

    protected function doGetValue(int $field)
    {
        return $this->fields[$field][static::K_VALUE] ?? null;;
    }

    /**
     * Returns an array of all child iterators
     */
    protected function getChildIterators() : array
    {
        return $this->getValue(static::F_CHILDREN);
    }

    /**
     * Returns an implementation of IUniqueModelIterator that will iterate over all child Models of
     * this Model that implement the given fully-qualified interface name, or null if there are none.
     * 
     * @param string $interface The interface name plus namespace of the child type you want to iterate
     * over
     * 
     * @return IUniqueModelIterator|null
     */
    protected function getChildIterator(string $interface) : ?IUniqueModelIterator
    {
        return $this->getValue(self::F_CHILDREN)[$interface] ?? null;
    }

    protected function setReadonlyValues(array $kvpairs) : void
    {
        foreach ( $kvpairs as $field => $value ) {
            $this->setReadOnlyValue($field, $value);
        }
    }

    protected function setReadOnlyValue(int $field, $value) : void
    {
        //validate against all EXCEPT read-only, throws exception on error
        $this->validate($field, $value, FieldException::ALL_VALIDATABLE & ~FieldException::ERR_READ_ONLY);
        $this->doSetValue($field, $value);
        $this->setDirty(true);
    }

    protected function doSetValue(int $field, $value) : void
    {
        //default set value logic
        $this->fields[$field]['value'] = $value;
    }

    protected function addFields(array $fields) : void
    {
        foreach ($fields as $field => $fieldDef) {
            $this->addField($field, $fieldDef);
        }
    }

    /**
     * Adds the given field definition to the $fields array at position indicated
     * by the given integer, overwriting any pre-existing fields in the process
     * to enable overriding of field definitions in subclasses.
     * 
     * @param int $field The integer key to use for the field; if a field already
     * exists for this value, it will be overwritten.
     * @param array $fieldDef Array of definition options for the field
     */
    protected function addField(int $field, array $fieldDef) : void
    {
        $this->fields[$field] = $fieldDef; //deliberately allow overwriting
    }

    protected function setParentType(string $type) : void
    {
        $this->setFieldType(static::F_PARENT, $type);
    }

    protected function setFieldType(int $field, string $type) : void 
    {
        $this->fields[$field][static::K_TYPE] = $type;
    }

    protected function getModelFactory() : IModelFactory
    {
        return $this->modelFactory;
    }
    #endregion

    #region Validation stuff
    protected function allValid(bool $throw = true) : int
    {
        foreach( $this->fields as $field => $fieldDef ) {
            $errors = $this->validate($field, $this->getValue($field), $throw); //throws on validation error if $throws is true
            if ($errors !== FieldException::NONE) {
                return $errors;
            }
        }

        return FieldException::NONE;
    }

    /**
     * Throws FieldException (or optionally returns a bitfield of errors) combined from the various FieldException constants 
     * that would apply to the field referred to by $field if it were to take $value.
     * 
     * Set $errors to a bitfield of constants from FieldException if you would like a subset of errors to be validated.
     * 
     * @throws FieldException if $throw is true and an error is encountered
     * 
     * @param int $field Key of the field to validate
     * @param mixed $value Value to check against the field definition
     * @param int $forErrors Bitfield of the errors to check for, or leave blank for all
     * @param bool $throw Whether to throw an exception on encountering an error, defaults to `true`
     * 
     * @return int All discovered errors, bitshifted into an int
     */
    protected function validate(int $field, $value, int $forErrors = FieldException::ALL_VALIDATABLE, bool $throw = true) : int
    {
        //always check for ERR_NOT_FOUND
        $errors = !$this->hasField($field) ? FieldException::ERR_NOT_FOUND : FieldException::NONE;

        $ftype = $this->fields[$field][static::K_TYPE] ?? null;
        $vtype = is_object($value) ? get_class($value) : (is_array($value) ? 'array' : gettype($value));
        if ($errors === FieldException::NONE ) {
            if ( ($forErrors & FieldException::ERR_READ_ONLY) !== 0) {
                $errors |= ($mode = $this->fields[$field][static::K_MODE] ?? '') == 'r' 
                    ? FieldException::ERR_READ_ONLY 
                    : FieldException::NONE;
            }

            if ($errors === FieldException::NONE) { //no valid values if the field is read-only
                if ( ($forErrors & FieldException::ERR_IS_NULL) !== 0) {
                    if ($this->isNull($value)) {
                        $errors |= ($this->fields[$field][static::K_OPTIONAL] ?? false) 
                            ? FieldException::NONE 
                            : FieldException::ERR_IS_NULL;
                    }
                }

                if ( $value && ($forErrors & FieldException::ERR_WRONG_TYPE) !== 0 ) {
                    $errors |= (!$ftype || ($ftype != $vtype))
                        ? FieldException::ERR_WRONG_TYPE 
                        : FieldException::NONE;
                }
            }
        }

        if ($errors !== FieldException::NONE && $throw) {
            $message =
                "Validating field $field, having K_TYPE = $ftype, with value of type $vtype
                \nDumping value
                \n". print_r($value, true);
            throw new FieldException($errors, $message);
        }

        return $errors;
    }

    protected function isNull($value) : bool
    {
        return static::T_NULL === $value || $value === null;
    }
    #endregion
}
