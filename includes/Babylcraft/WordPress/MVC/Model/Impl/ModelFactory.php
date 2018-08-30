<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IModelFactory;
use Babylcraft\WordPress\MVC\Model\Impl\CalendarFactory;
use Babylcraft\WordPress\DBAPI;
use Babylcraft\WordPress\MVC\Model\ModelException;


class ModelFactory implements IModelFactory
{
    use DBAPI;

    const OPT_HAS_SCHEMA_PREFIX = "has_schema_";

    protected $pdo;
    protected $wpdb;
    private $tableNamespace;

    private $calendarFactory;

    public function setDBConnections(
        \PDO $pdo, 
        \wpdb $wpdb,
        string $tableNamespace = 'babyl_')
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->wpdb = $wpdb;
        $this->tableNamespace = $tableNamespace;

        $this->calendarFactory = new CalendarFactory($pdo, $wpdb, $tableNamespace);
    }

    public function cloneDBConnections(IModelFactory $to) : void
    {
        if ($to instanceof ModelFactory) {
            $to->setDBConnections($this->pdo, $this->wpdb, $this->tableNamespace);
            return;
        }

        throw new \BadMethodCallException("\$to must be an instance of ModelFactory or one of its subclasses. ");
    }

    public function createCalendarSchema() : void 
    {
        $this->createOrDeleteSchema(CalendarFactory::class, $delete = false);
    }

    public function deleteCalendarSchema() : void
    {
        $this->createOrDeleteSchema(CalendarFactory::class, $delete = true);
    }

    protected function createOrDeleteSchema(string $className, bool $delete = false)
    {
        // if (!$delete && $this->hasSchema($className)) {
        //     throw new ModelException(ModelException::ERR_SCHEMA_EXISTS, $className);
        // } else if ($delete && !$this->hasSchema($className)) {
        //     throw new ModelException(ModelException::ERR_SCHEMA_NOT_EXISTS, $className);
        // }

        $getSchemaMethod = new \ReflectionMethod($className, "getSchema");
        $this->pdo->exec(
            $getSchemaMethod->invoke(
                null,
                $this->tableNamespace,
                $this->getWPTablePrefix(),
                $this->getCharsetCollate(),
                $delete
            )
        );

        //TODO Replace this with something that I can rely on giving useful return value
        //from the docs: "update_option returns true if option value has changed, false if not or if update failed."
        $this->setHasSchema($className, !$delete);
        // if ($this->setHasSchema($className, !$delete) === false) {
        //     //attempt to rollback schema creation
        //     // $this->pdo->exec(
        //     //     $getSchemaMethod->invoke(
        //     //         null,
        //     //         $this->tableNamespace,
        //     //         $this->getWPTablePrefix(),
        //     //         $this->getCharsetCollate(),
        //     //         !$delete
        //     //     )
        //     // );

        //     throw new ModelException(ModelException::ERR_OPTION_UPDATE_FAILED, $this->hasSchemaOption($className));
        // }
    }

    protected function configure(BabylonModel $model) : void
    {
        $model->configureDB($this->pdo, $this->wpdb, $this->tableNamespace);
        $model->setModelFactory($this);
    }

    protected function hasSchema(string $className) : bool
    {
        return $this->getOption($this->hasSchemaOption($className));
    }

    protected function setHasSchema(string $className, bool $hasSchema) : bool
    {
        return $this->setOption($this->hasSchemaOption($className), true);
    }

    protected function hasSchemaOption(string $className) : string
    {
        return ModelFactory::OPT_HAS_SCHEMA_PREFIX 
            . substr($className, strrpos($className, "\\") + 1); //chops off the namespace
    }
}