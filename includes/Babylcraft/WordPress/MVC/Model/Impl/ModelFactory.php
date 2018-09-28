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

    protected $mappings = [];
    protected $reverseMappings = [];

    const DEFAULT_MAPPINGS = [
        ICalendarModel::class => CalendarModel::class,
        IEventModel::class    => EventModel::class
    ];

    public function __construct($mappings = null)
    {
        $this->mappings = array_merge(
            $this->mappings,
            $mappings ?? static::DEFAULT_MAPPINGS
        );

        $this->reverseMappings = array_flip($this->mappings);
    }

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

    public function getImplementingClass(string $interface) : string
    {
        if (array_key_exists($interface, $this->mappings)) {
            return $this->mappings[$interface];
        }

        throw new ModelException(ModelException::ERR_UNKNOWN_MAPPING, "Was given $interface. ");
    }

    public function getModelInterface(IBabylonModel $model): string
    {
        if (array_key_exists(get_class($model), $this->reverseMappings)) {
            return $this->reverseMappings[get_class($model)];
        }

        throw new ModelException(ModelException::ERR_UNKNOWN_MAPPING, "Was given object with class ". get_class($model) .". ");
    }

    public function createCalendarSchema() : void 
    {
        $this->createOrDeleteSchema(ICalendarModel::class, $delete = false);
    }

    public function deleteCalendarSchema() : void
    {
        $this->createOrDeleteSchema(ICalendarModel::class, $delete = true);
    }

    public function calendar(string $owner, string $uri, string $tz = 'UTC', array $fields = []) : ICalendarModel
    {
        return $this->withSparkles(
            $this->getImplementingClass(ICalendarModel::class)::createCalendar($owner, $uri), $fields);
    }

    public function event(ICalendarModel $calendar, string $name, string $rrule, \DateTime $start, array $fields = []): IEventModel
    {
        return $this->withSparkles(
            $this->getImplementingClass(IEventModel::class)::createEvent($calendar, $name, $rrule, $start), $fields);
    }

    public function eventVariation(IEventModel $event, string $name, string $rrule, array $fields = []) : IEventModel
    {
        return $this->withSparkles(
            $this->getImplementingClass(IEventModel::class)::createVariation($event, $name, $rrule), $fields);
    }

    protected function withSparkles(IBabylonModel $model, array $fields = []) : IBabylonModel
    {
        //make iterator objects for the declared child types of the new Model object
        $iterators = [];
        foreach ( $model->getChildTypes() as $childType ) {
            $iterators[$this->getImplementingClass($childType)] = new UniqueModelIterator();
        }
        
        //augment $fields with the iterators
        $fields[IBabylonModel::F_CHILDREN] = $iterators;

        //set all field values on the new Model
        $model->setValues($fields);        
        
        //configure the model with DB access
        $model->configureDB($this->pdo, $this->wpdb, $this->tableNamespace);

        //give it the ability to make new Model objects (children etc)
        $model->setModelFactory($this);
        
        return $model;
    }

    protected function createOrDeleteSchema(string $interface, bool $delete = false)
    {
        $getSchemaMethod = new \ReflectionMethod(
            $this->getImplementingClass($interface),
            "getSchema"
        );

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
            $this->setHasSchema($interface, !$delete);
        }
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