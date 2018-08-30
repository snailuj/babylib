<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IModelFactory;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Babylcraft\WordPress\DBAPI;


abstract class BabylonModel implements IBabylonModel {
    const DB_NULL = "NULL";
    
    const FIELDS_DEFAULT = [
        "id" => [
            "dbFieldName" => "id",
            "value" => -1 //-1 indicates not saved yet
        ]
    ];

    protected $pdo;
    protected $wpdb;
    protected $modelFactory;

    private $fields;
    private $tableNamespace;

    public function configureDB(
        \PDO $pdo,
        \wpdb $wpdb,
        string $tableNamespace = 'babyl_',
        string $wpTableNamespace = 'wp_'
    ) {
        $this->pdo = $pdo;
        $this->wpdb = $wpdb;
        $this->tableNamespace = $tableNamespace;

        $this->setupFields();
    }

    protected function setupFields() : void
    {
        $fields = BabylonModel::FIELDS_DEFAULT;
    }

    abstract static protected function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false
    ) : string;

    public function getFields() : array
    {
        return $fields;
    }

    public function hasField(string $fieldName) : bool
    {
        return is_set($this->getFields()[$fieldName]);
    }

    public function setField(string $fieldName, $value) : void
    {
        if (!$this->hasField($fieldName)) {
            throw new ModelException(ModelException::ERR_FIELD_NOT_FOUND);
        }

        $this->fields[$fieldName]['value'] = $value;
    }

    protected function addFields(array $fields) : void
    {
        foreach ($fields as $fieldName => $fieldDef) {
            $dbFieldName = isset($fieldDef['dbFieldName']) ? : BabylonModel::DB_NULL;
            $defaultvalue = isset($fieldDef['defaultValue']) ? : BabylonModel::DB_NULL;

            $this->addField($fieldName, $dbFieldName, $defaultValue);
        }
    }

    private function addField(string $fieldName, string $dbFieldName, mixed $defaultValue) : void
    {
        $fields = &$this->getFields(); //'&' because otherwise PHP does a value copy
        $fields[$fieldName] = [
            'dbFieldName' => $dbFieldName,
            'value' => $defaultValue
        ];
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
}
