<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\DBAPI;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\IModelFactory;


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
        $this->createOrDeleteSchema(CalendarModel::class, $delete = false);
    }

    public function deleteCalendarSchema() : void
    {
        $this->createOrDeleteSchema(CalendarModel::class, $delete = true);
    }

    public function calendar(string $owner, string $uri, string $tz = 'UTC', array $fields = []) : ICalendarModel
    {
        return $this->withSparkles(CalendarModel::calendar($owner, $uri), $fields);
    }

    public function event(ICalendarModel $calendar, string $name, string $rrule, \DateTime $start, array $fields = []): IEventModel
    {
        return $this->withSparkles(EventModel::event($calendar, $name, $rrule, $start), $fields);
    }

    public function eventVariation(IEventModel $event, string $name, string $rrule, array $fields = []) : IEventModel
    {
        return $this->withSparkles(EventModel::createVariation($event, $name, $rrule), $fields);
    }

    protected function withSparkles(IBabylonModel $model, array $fields = []) : IBabylonModel
    {
        $model->setValues($fields);
        $this->configure($model);

        return $model;
    }

    protected function createOrDeleteSchema(string $className, bool $delete = false)
    {
        $getSchemaMethod = new \ReflectionMethod($className, "getSchema");
        $sql = $getSchemaMethod->invoke(
            null,
            $this->tableNamespace,
            $this->getWPTablePrefix(),
            $this->getCharsetCollate(),
            $delete
        );
        
        if ($sql) {
            $this->pdo->exec($sql);
            //TODO Replace this with something that I can rely on giving useful return value
            //from the docs: "update_option returns true if option value has changed, false if not or if update failed."
            $this->setHasSchema($className, !$delete);
        }
    }

    protected function configure(BabylonModel $model) //mixed return type to avoid PHP's lack of class-casting in subclasses
    {
        $model->configureDB($this->pdo, $this->wpdb, $this->tableNamespace);
        $model->setModelFactory($this);

        return $model;
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