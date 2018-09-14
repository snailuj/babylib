<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IModelFactory;
use Babylcraft\WordPress\MVC\Model\ModelException;

use Babylcraft\WordPress\MVC\Model\HasPersistence;
use Babylcraft\WordPress\MVC\Model\FieldException;


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
        $this->dirty = true; //if created from code, needs DB save
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

    #region IBabylonModel Implementation
    /**
     * Load from storage via FIELD_ID
     * @see IBabylonModel::loadRecord()
     */
    public function loadRecord() : void
    {
        if (!$this->doLoadRecord()) {
            //do default load process
        }

        $model->dirty = false;
    }

    /**
     * @see IBabylonModel::save()
     */
    public function save() : void
    {
        if (!$this->isDirty()) {
            return;
        }

        $this->allValid();

        if( $this->getValue(self::FIELD_ID) == -0x1 ) {
            if (!$this->doCreateRecord()) {
                //do default create logic
            }
        } else {
            if (!$this->doUpdateRecord()) {
                //do default update logic
            }
        }

        $this->dirty = false;
    }

    /**
     * @see IBabylonModel::getFields()
     */
    public function getFields() : array
    {
        return $this->fields;
    }

    /**
     * @see IBabylonModel::getSerializable()
     */
    public function getSerializable(): array
    {   //override in subclasses if you need to trim / augment values for serialization
        $map = [];
        foreach( $this->fields as $field => $fieldDef ) {
            if (isset($fieldDef[static::K_NAME])) { //if K_NAME is not set then it's a transient prop
                \Babylcraft\WordPress\PluginAPI::debug("getSerializable() : field number: ". $field ." has name ". $fieldDef[static::K_NAME]);
                $map[$fieldDef[static::K_NAME]] = $this->getValue($field);
            }
        }

        return $map;
    }

    /**
     * @see IBabylonModel::getId()
     */
    public function getId() : int
    {
        return $this->getValue(static::FIELD_ID);
    }

    /**
     * @see IBabylonModel::getValue()
     */
    public function getValue(int $field)
    {
        if ( null === ($val = $this->doGetValue($field)) ) {
            //null indicates "run default getValue logic"
            $val = $this->fields[$field]['value'] ?? null;
        }

        return $this->isNull($val) ? null : $val;
    }

    /**
     * @see IBabylonModel::hasField()
     */
    public function hasField(int $field) : bool
    {
        return array_key_exists($field, $this->getFields());
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
        //validate against all validatable errors, throws exception if invalid
        $this->validate($field, $value);
        $this->doSetValue($field, $value);
    }
    #endregion

    #region Protected Subclassy stuff
    protected function setupFields() : void
    {
        $this->addFields(self::FIELDS_DEFAULT);
    }

    protected function doLoadRecord() : bool
    {
        ; //TODO consider making all these doStuff() functions abstract once the behaviour is more fleshed out
    }

    protected function doCreateRecord() : bool
    {
        return false; //fallback on default create() above
    }

    protected function doUpdateRecord() : bool
    {
        return false; //fallback on default update() above
    }

    /**
     * Return null from this function ONLY to indicate that you are not interested in overriding
     * the given field -- the calling function will take that as a flag to run the default getValue
     * logic.
     * 
     * If you need to indicate a valid null value for the given field, then return T_NULL instead.
     */
    protected function doGetValue(int $field)
    {
        return null; //fallback on default getValue() above
    }

    protected function isDirty() : bool
    {
        return $this->dirty;
    }

    protected function setReadOnlyValue(int $field, $value) : void
    {
        //validate against all EXCEPT read-only, throws exception on error
        $this->validate($field, $value, FieldException::ERR_ALL_VALIDATABLE & ~FieldException::ERR_READ_ONLY);
        $this->doSetValue($field, $value);
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

    protected function addField(int $field, array $fieldDef) : void
    {
        if (array_key_exists($field, $this->fields)) {
            throw new FieldException(FieldExceptioN::ERR_ALREADY_DEFINED, $fieldName ."(key = $field)");
        }

        $this->fields[$field] = $fieldDef;
    }

    protected function setParentType(string $type) : void
    {
        $this->setFieldType(static::FIELD_PARENT, $type);
    }

    protected function setFieldType(int $field, string $type) : void 
    {
        $this->fields[$field][static::K_TYPE] = $type;
    }

    protected static function setParent(IBabylonModel $parent, IBabylonModel $child) : void
    {
        $child->setValue(static::FIELD_PARENT, $parent);
    }

    public function getParent()
    {
        return $this->getValue(static::FIELD_PARENT) ?? null;
    }

    public function setModelFactory(IModelFactory $modelFactory) : void
    {
        $this->modelFactory = $modelFactory;
    }

    protected function getModelFactory() : IModelFactory
    {
        return $this->modelFactory;
    }

    protected function getBabylTablePrefix() : string
    {
        return $this->getTablePrefix() .'_'. $this->tableNamespace;
    }
    #endregion

    #region Protected Validation stuff
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
    protected function validate(int $field, $value, int $forErrors = FieldException::ERR_ALL_VALIDATABLE, bool $throw = true) : int
    {
        //always check for ERR_NOT_FOUND
        $errors = !$this->hasField($field) ? FieldException::ERR_NOT_FOUND : FieldException::NONE;

        $ftype = $this->fields[$field][static::K_TYPE] ?? null;
        $vtype = is_object($value) ? get_class($value) : (is_array($value) ? 'array' : gettype($value));
        if ($errors === FieldException::NONE ) {
            if ( ($forErrors & FieldException::ERR_READ_ONLY) !== 0) {
                $errors |= ($mode = $this->fields[$field][static::K_MODE] ?? '') == 'r' ? FieldException::ERR_READ_ONLY : 0;
            } else { //no valid values if the field is read-only
                if ( ($forErrors & FieldException::ERR_IS_NULL) !== 0) {
                    if ($this->isNull($value)) {
                        $errors |= ($this->fields[$field][static::K_OPTIONAL] ?? false) ? 0 : FieldException::ERR_IS_NULL;
                    }
                }

                if ( ($forErrors & FieldException::ERR_WRONG_TYPE) !== 0) {
                    $errors |= ($value && (!$ftype || ($ftype != $vtype))) ? FieldException::ERR_WRONG_TYPE : 0;
                }
            }
        }

        if ($errors !== FieldException::NONE && $throw) {
            $message = "Validating field $field, having K_TYPE = $ftype, with value $value of type $vtype";
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
