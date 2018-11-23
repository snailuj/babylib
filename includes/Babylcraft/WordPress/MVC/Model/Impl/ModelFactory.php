<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\DBAPI;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\IModelFactory;
use Sabre\VObject\Component\VEvent;
use Babylcraft\WordPress\MVC\Model\Sabre\SabreFacade;
use Babylcraft\WordPress\MVC\Model\IDataMapper;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\Util;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Property\ICalendar\Recur;
use Babylcraft\WordPress\MVC\Model\Sabre\IVObjectClient;


class ModelFactory implements IModelFactory
{
    use DBAPI;

    const OPT_HAS_SCHEMA_PREFIX = "has_schema_";

    /**
     * @var SabreFacade Object that makes the Sabre API more codey and 
     * less iCalendary
     */
    protected $sabre;

    protected $pdo;
    protected $wpdb;
    protected $tableNamespace;

    protected $mappings = [];
    protected $reverseMappings = [];

    protected $insertStatements = [];
    protected $selectStatements = [];
    protected $deleteStatements = [];
    protected $updateStatements = [];
   
    const SELECT_OR  = 0x1;
    const SELECT_AND = 0x2;
    const SELECT_IN  = 0x4;

    const DEFAULT_MAPPINGS = [
        ICalendarModel::class => CalendarModel::class,
        IEventModel::class    => EventModel::class
    ];

    public function __construct()
    {
        $this->mappings = array_merge(
            $this->mappings,
            static::DEFAULT_MAPPINGS
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
        $this->sabre = new SabreFacade($pdo, $tableNamespace);
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

    public function newCalendar(string $owner, string $uri, string $tz = 'UTC') : ICalendarModel
    {
        return $this->withSparkles(
            $this->getImplementingClass(ICalendarModel::class)::construct($owner, $uri, $tz)
        );
    }

    public function newEvent(
        ICalendarModel $parent,
        string $name,
        string $rrule,
        \DateTimeInterface $start,
        string $uid
    ): IEventModel
    {
        $model = $this->withSparkles(
            $this->getImplementingClass(IEventModel::class)::construct(
                $parent,
                $name,
                $rrule,
                $start,
                $uid !== '' ? $uid : \Babylcraft\Util::generateUid()
            )
        );
    }

    public function newVariation(
        IEventModel $parent,
        string $name,
        string $rrule,
        string $uid
    ) : IEventModel
    {
        return $this->withSparkles(
            $this->getImplementingClass(IEventModel::class)::construct(
                $parent,
                $name,
                $rrule,
                null,
                $uid !== '' ? $uid : \Babylcraft\Util::generateUid()
            )
        );
    }

    /**
     * @see IModelFactory::persist()
     */
    public function persist(IBabylonModel $model) : void
    {
        if ( $model->getValue(IDataMapper::F_ID) === IDataMapper::DEFAULT_ID ) {
            $this->createRecord($model);
        } else {
            $this->updateRecord($model);
        }
    }
    
    /**
     * @see IModelFactory::hydrate()
     */
    public function hydrate(IBabylonModel $model) : void
    {
        $this->hydrateSingle($model, IDataMapper::F_ID);
    }

    /**
     * @see IModelFactory::newHydratedChildren()
     */
    public function newHydratedChildren(IBabylonModel $model, ?string $childInterfaceName = null) : void
    {
        $childTypes = $childInterfaceName ? [$childInterfaceName] : $model->getChildTypes();
        foreach ( $childTypes as $childType ) {
            if ( count($children = $this->newAllChildrenOfType($model, $childType)) ){
                $model->addChildren($children);
            }
        }
    }

    //  - get update / insert to retrieve proper foreign key ID from getParent()->getId()

    #region CRUD helpers
    protected function newAllChildrenOfType(IBabylonModel $model, string $childInterfaceName) : array
    {
        //children of VCalendar / VEvent are loaded all at once due to Sabre behaviour
        //so getting here with an instance of that type is an error we want to let the developer know about
        if ( $model instanceof ICalendarModel ||
             $model instanceof IEventModel ) {
            throw new ModelException(ModelException::ERR_BAD_QUERY, "CalendarModel and EventModel do not support lazy-loading of children.");
        }

        //load all models that have parent id equal to the given model's ID
        return $this->newAnyIn(
            $this->getMapperFor($childInterfaceName),
            IDataMapper::F_PARENT,
            $model->getId()
        );
    }

    /**
     * Load data for all records mapped by $recordMap where ANY field identified by $fieldPack is IN the
     * corresponding set of values given by $valueMap, and create object instances for each of them, populated
     * with the loaded data.
     * 
     * Bits ORed into $fieldPack indicate the field names to select by, and must equate to fields having K_NAME
     * values on the given IDataMapper implementation. Packing a field into $fieldPack that does not exist
     * on the interface given by $recordMap, or that does not have a K_NAME entry, or that does not exist as
     * a key in $valueMap is an error and will raise an exception.
     * 
     * $valueMap should be an associative array of the form [
     *  K_NAME => [valueA, valueB, ...],
     *  K_NAME => [valueX, valueY, ...]
     * ].
     * 
     * The keys will be matched up with the corresponding K_NAMEs identified by values packed into $fieldPack.
     * 
     * If $valueMap is empty, no records will be returned.
     */
    protected function newAnyIn(IDataMapper $recordMap, int $fieldPack, array $valueMap) : array
    {
        if (!count($valueMap)) {
            return [];
        }
        
        return $this->newAndLoad(
            $recordMap,
            $this->getSelectAnyInStatement($recordMap, $fieldPack),
            $valueMap
        );
    }

    /**
     * Load data for all records mapped by $recordMap where ALL fields identinewAndLoadfied by $fieldPack equal corresponding
     * values in $valueMap, and create object instances for each of them, populated with the loaded data.
     * 
     * Bits ORed into $fieldPack indicate the field names to select by, and must equate to fields having K_NAME
     * values on the interface identified by $recordMap. Packing a field into $fieldPack that does not exist
     * on the interface given by $recordMap, or that does not have a K_NAME entry, or that does not exist as
     * a key in $valueMap is an error and will raise an exception.
     * 
     * $valueMap should be an associative array of the form [
     *  K_NAME => valueA,
     *  K_NAME => valueB
     * ].
     * 
     * The keys will be matched up with corresponding K_NAMEs identified by values packed into $fieldPack.
     * 
     * if $valueMap is empty, no records will be returned.
     */
    protected function newAllEqual(string $recordMap, int $fieldPack, array $valueMap) : array
    {
        if (!count($valueMap)) {
            return [];
        }

        return $this->newAndLoad(
            $recordMap,
            $this->getSelectAllEqualStatement($recordMap, $fieldPack),
            $valueMap
        );
    }

    /**
     * Uses data from executing the given statement with the given values to instantiate and populate one
     * or more objects that implement the given interface, one row per object.
     */
    protected function newAndLoad(IDataMapper $recordMap, \PDOStatement $statement, array $values) : array
    {
        //as for hydrateSingle, add caching in here
        try {
            $data = $statement->execute($values)->fetchAll();
        } catch ( \PDOException $e ) {
            throw Util::newModelPDOException($e, $statement);
        }

        $models = array_map(function($row) use ($recordMap) {
            $model = $this->withSparkles(
                (new $this->getModelClass($recordMap))()
            );
            
            $model->loadSerializeable($row);

            return $model;
        }, $data);

        return $models;
    }

    /**
     * Hydrates the given model with data from storage using a condition created by logical ANDing 
     * all the fields identified by the given field bit-pack.
     * 
     * @throws ModelException ModelException::ERR_BAD_SELECT if more or less than one record is retrieved
     * by the generated query
     */
    protected function hydrateSingle(IBabylonModel $model, int $byFieldPack) : void
    {
        //TODO add caching in here e.g. to Redis or something if it seems like a bottleneck
        if ($model instanceof ICalendarModel) {
            $vcalendar = $this->sabre->loadVCalendar($model);
            $this->vcalendarToCalendarModel($vcalendar, $model);

            \Babylcraft\WordPress\PluginAPI::debugContent(json_encode(@$vcalendar->jsonSerialize()), "ModelFactory::load(calendar)");
        } else if ($model instanceof IEventModel ) {
            $vevent = $this->sabre->loadVEvent($model);
            $this->veventToEventModel($vevent, $model);

            \Babylcraft\WordPress\PluginAPI::debugContent(json_encode(@$vevent->jsonSerialize()), "ModelFactory::load(event)");
        } else {
            $statement = $this->getSelectAllEqualStatement($model, $byFieldPack);
            try {
                $data = $statement->execute(
                            $model->getSerializable($byFieldPack))->fetchAll();
            } catch ( \PDOException $e ) {
                throw Util::newModelPDOException($e, $statement);
            }

            if (count($data) !== 1) {
                throw new ModelException(ModelException::ERR_BAD_SELECT, "When loading model with class ". get_class($model));
            }

            $model->loadSerializeable($data);
        }
    }

    protected function createRecord(IBabylonModel $model) : void
    {
        $id = IDataMapper::DEFAULT_ID;
        if ($model instanceof ICalendarModel) {       
            $id = $this->sabre->createCalendar($model);
        } else if ($model instanceof IEventModel) {
            $id = $this->sabre->createEvent($model);
        } else {
            $statement = $this->getInsertStatement($model);
            try {
                $statement->execute($model->getSerializable());
            } catch ( \PDOException $e ) {
                throw Util::newModelPDOException($e, $statement);
            }

            if ( $statement->rowCount() !== 1 ) {
                throw new ModelException(ModelException::ERR_INCORRECT_ROW_COUNT, "When creating record with class ". get_class($model));
            }
            
            $id = $this->pdo->lastInsertId();
        }

        if ($id === IDataMapper::DEFAULT_ID) { //$id should have changed by now
            throw new ModelException(ModelException::ERR_NO_ID, "When saving model with class ". get_class($model));
        }

        $this->setReadonlyModelValue($model, IDataMapper::F_ID, $id);
        $this->setModelDirty($model, false); //model does not need saving
    }

    protected function updateRecord(IBabylonModel $model) : void
    {
        if ($model instanceof ICalendarModel ) {
            $this->sabre->updateCalendar($model);
        } else if ($model instanceof IEventModel) {
            $this->sabre->updateEvent($model);
        } else {
            $statement = $this->getUpdateStatement($model->getSerializable());
            try {
                $statement->execute($fields);
            } catch (\PDOException $e) {
                throw Util::newModelPDOException($e, $statement);
            }
            
            if ( $statement->rowCount() !== 1 ) {
                throw new ModelException(ModelException::ERR_INCORRECT_ROW_COUNT, "When updating record with class ". get_class($model));
            }
        }
    }
    #endregion

    #region Sabre-to-Model copying methods
    /**
     * These sabre-to-model functions are only called when marshalling existant DB records to in-memory Model
     * objects. We can assume that the IDs and UIDs etc on the VObjects are already present -- there is no need
     * to regenerate them when creating the Model.
     */
    protected function vcalendarToCalendarModel(VCalendar $from, ICalendarModel $to, bool $deep = true) : void
    {
        $to->setValues([
            ICalendarModel::F_TZ    => $from->TZID->getValue(),
            IDataMapper::F_ID       => \explode(",", $from->ID) //seriously sabre WTF
        ]);

        $this->setReadonlyModelValues($to, [
            ICalendarModel::F_OWNER => $from->PRINCIPALURI->getValue(),
            ICalendarModel::F_URI   => $from->URI->getValue()
        ]);

        $this->setModelDirty($to, false); //model does not need saving
        
        //$to->setVObjectFactory($this->sabre);

        $vevents = [];
        foreach ( $from->VCALENDAR as $eventWrapper ) {
            foreach ( $eventWrapper->VEVENT as $vevent ) {
                $vevents[] = $vevent;
            }
        }

        $iter = new \Sabre\VObject\Recur\EventIterator($vevents);
        $vevents[] = 'test';

        if ($deep) {
            $this->veventsToEventModels($from, $to, $deep);
        }
    }

    protected function veventsToEventModels(VCalendar $from, ICalendarModel $to, bool $deep = true) : void
    {
        foreach ( $from->VCALENDAR as $eventWrapper ) {
            foreach ( $eventWrapper->VEVENT as $vevent ) {
                $this->veventToEventModel($vevent, $to, $deep);
            }
        }
    }

    protected function veventToEventModel(VEvent $from, ICalendarModel $parent, bool $deep = true) : IEventModel
    {
        //try and get the event model in $parent that matches the UID in $from
        if ( $eventModel = $parent->getEvent($from->URI->getValue()) ) {
            //found it, so just update the values that we have. No need to update UID
            //because it's already set
            $eventModel->setValues([
                IEventModel::F_RRULE => $from->RRULE->getValue(),
                IEventModel::F_START => $from->DTSTART->getDateTime()
            ]);

            $this->setReadonlyModelValue($eventModel, IEventModel::F_NAME, $from->SUMMARY->getValue());
            $this->setModelDirty($eventModel, false); //model does not need saving
        } else {
            //not found, so create one and load the values into it
            //we use the UID from the VEvent because these sabre-to-model functions
            //are only called when going from existant DB records to in-memory Model objects
            //therefore we can assume the UID has already been generated before the 
            //data was persisted
            $eventModel = $parent->addEvent(
                $from->URI->getValue(),
                $from->RRULE->getValue(),
                $from->DTSTART->getDateTime(),
                $from->UID->getValue()
            );

            //$eventModel->setVObjectFactory($this->sabre);
        }
        
        if ($deep) {
            $this->exrulesToVariations($from, $eventModel);
        }

        return $eventModel;
    }

    protected function exrulesToVariations(VEvent $from, IEventModel $parent) : void
    {
        foreach ( $from->EXRULE as $exrule ) {
            //exrule already exists on the parent?
            if ( $variation = $this->getVariationForExrule($parent, $exrule) ) {
                //then just copy the values into it
                $this->exruleToVariation($exrule, $variation);
            } else {
                //else create a new one
                //we use the UID from the EXRULE because these sabre-to-model functions
                //are only called when going from existant DB records to in-memory Model objects
                //therefore we can assume the UID has already been generated before the 
                //data was persisted
                $variation = $parent->addVariation(
                    $exrule->offsetGet(
                        $parent->getFieldName(IEventModel::F_NAME)
                    )->getValue(),
                    $exrule->getValue(),
                    $exrule->offsetGet(
                        $parent->getFieldName(IEventModel::F_UID)
                    )->getValue()
                );
            }
        }
    }

    protected function exruleToVariation(Recur $from, IEventModel $to) : void
    {
        $to->setValue(IEventModel::F_RRULE, $from->getValue());
        $this->setReadonlyModelValue(
            $to,
            IEventModel::F_NAME,
            $from->offsetGet(
                $to->getFieldName(IEventModel::F_NAME)
            )->getValue()
        );

        $this->setModelDirty($to, false); //model does not need saving
    }

    /**
     * Returns a variation with the same UID as the given Recur object, or null if not
     * exists on the given IEventModel object.
     */
    protected function getVariationForExrule(IEventModel $parent, Recur $exrule) : ?IEventModel
    {
        return $parent->getVariation(
            //this baroque line of code retrieves the UID from the Recur object
            $exrule->offsetGet($parent->getFieldName(IEventModel::F_UID))->getValue()
        );
    }
    #endregion

    #region PDOStatement constructors
    protected function getInsertStatementFor(IDataMapper $mapper) : \PDOStatement
    {
        if ( null == ($this->insertStatements[get_class($mapper)] ?? null) ) {
            $names = [];
            $values = [];
            foreach( $mapper->getFields() as $code => $defn ) {
                if ( $name = $defn[IDataMapper::K_NAME] ?? null) {
                    $names[]  = $name;
                    $values[] = ':'. $name;
                }
            }

            $sql = sprintf(
                'INSERT INTO %s%s (%s) VALUES (%s)',
                $this->tableNamespace,
                $mapper->getRecordName(),
                implode(', ', $names),
                implode(', ', $values)
            );

            $this->insertStatements[get_class($mapper)] = $this->pdo->prepare($sql);
            \Babylcraft\WordPress\PluginAPI::debugContent($sql, "ModelFactory Generated Query");
        }

        return $this->insertStatements[get_class($mapper)];
    }

    protected function getUpdateStatementFor(IDataMapper $mapper) : \PDOStatement
    {
        if ( null == ($this->updateStatements[get_class($mapper)] ?? null) ) {
            $fieldNames = $mapper->getUpdateableNames();
            $first = array_pop($fieldNames);
            $fmt = array_reduce(
                $fieldNames,
                function($carry, $item) {
	                return sprintf($carry, sprintf(', %1$s = :%1$s %%s', $item));
                },
                sprintf(' SET %1$s = :%1$s %%s', $first)
            );

            $sql = sprintf(
                'UPDATE %s%s %s ',
                $this->tableNamespace,
                $mapper->getRecordName(),
                sprintf(
                    $fmt, 
                    ' WHERE %1$s = :%1$s;',
                    $mapper->getFieldName(IDataMapper::F_ID)
                )
            );

            $this->updateStatements[get_class($mapper)] = $this->pdo->prepare($sql);
            \Babylcraft\WordPress\PluginAPI::debugContent($sql, "ModelFactory Generated Query");
        }

        return $this->updateStatements[get_class($mapper)];
    }

    protected function getDeleteStatementFor(IDataMapper $mapper) : \PDOStatement
    {
        if ( (null == $this->deleteStatements[get_class($mapper)] ?? null) ) {
            $sql = sprintf(
                'DELETE FROM %s%s WHERE %1$s = :%1$s',
                $this->tableNamespace,
                $mapper->getRecordName(),
                $mapper->getFieldName(IDataMapper::F_ID)
            );

            $this->deleteStatements[get_class($mapper)] = $this->pdo->prepare($sql);
            \Babylcraft\WordPress\PluginAPI::debugContent($sql, "ModelFactory Generated Query");
        }

        return $this->deleteStatements[get_class($mapper)];
    }

    protected function getSelectAnyInStatement(IDataMapper $mapper, int $fieldPack) : \PDOStatement
    {
        if (null == $this->getSelectStatement($mapper, $fieldPack, IDataMapper::SELECT_IN | static::SELECT_OR)) {
            $fieldNames = $mapper->getFieldNames($fieldPack);
            $first = array_pop($fieldNames);
            if (null == $valueMap[$first] ?? null) {
                throw new ModelException(ModelException::ERR_BAD_QUERY, "$first not found in valueMap");
            }

            $where = array_reduce(
                $fieldNames,
                function($carry, $item) {
                    if (null == $valueMap[$item] ?? null) {
                        throw new ModelException(ModelException::ERR_BAD_QUERY, "$item not found in valueMap");
                    }

                    return sprintf($carry, " OR %s IN (%s) %%s", implode(", ", $valueMap[$item]));
                },
                sprintf(' WHERE %s IN (%s) %%s', implode(", ", $valueMap[$first]))
            );

            $sql = sprintf('SELECT %s FROM %s%s %s',
                implode(', ', $mapper->getUpdateableNames()),
                $this->tableNamespace,
                $mapper->getValue(IDataMapper::F_TABLE_NAME),
                sprintf($where, ';')
            );

            $this->setSelectStatementFor(
                $mapper,
                $fieldPack,
                static::SELECT_IN | static::SELECT_OR,
                $this->pdo->prepare($sql));

            \Babylcraft\WordPress\PluginAPI::debugContent($sql, "ModelFactory Generated Query");
        }

        return $this->getSelectStatement($mapper, $fieldPack, static::SELECT_IN | static::SELECT_OR);
    }

    /**
     * Set $byFieldPack = 0 if you want to get all records for the table (why would you though?)
     */
    protected function getSelectAllEqualStatement(IDataMapper $mapper, int $byFieldPack) : \PDOStatement
    {
        //special case if byFieldPack == 0
        if (0 === $byFieldPack) {
            return $this->getSelectAllStatementFor($mapper);
        }

        if ( (null == $this->getSelectStatement($mapper, $byFieldPack, static::SELECT_AND)) ) {
            $fieldNames = $mapper->getFieldNames($byFieldPack);
            $first = array_pop($fieldNames);
            $where = array_reduce(
                $fieldNames,
                function($carry, $item) {
                    return sprintf($carry, sprintf(' AND %1$s = :%1$s %%s', $item));
                },
                sprintf(' WHERE %1$s = :%1$s %%s', $first)
            );

            $sql = sprintf(
                'SELECT %s FROM %s%s %s',
                implode(", ", $mapper->getUpdateableNames()),
                $this->tableNamespace,
                $mapper->getRecordName(),
                sprintf($where, ';')
            );

            $this->setSelectStatementFor($mapper, $byFieldPack, static::SELECT_AND, $this->pdo->prepare($sql));
            \Babylcraft\WordPress\PluginAPI::debugContent($sql, "ModelFactory Generated Query");
        }

        return $this->getSelectStatement($mapper, $byFieldPack, static::SELECT_AND);
    }

    protected function getSelectAllStatementFor(IDataMapper $mapper)
    {
        if ( (null == $this->getSelectStatement($mapper, 0, 0)) ) {
            $sql = sprintf(
                'SELECT %s from %s%s',
                implode(", ", $mapper->getUpdateableNames()),
                $this->tableNamespace,
                $mapper->getRecordName()
            );

            $this->setSelectStatementFor($mapper, 0, 0, $this->pdo->prepare($sql));
            \Babylcraft\WordPress\PluginAPI::debugContent($sql, "ModelFactory Generated Query");
        }

        return $this->getSelectStatement($mapper, 0, 0);
    }

    //$byFieldPack is a bit-packed OR of the keys you want to query by
    //$selectLogic is a bit-packed OR of the type of select that the query performs, e.g. SELECT_IN | SELECT_AND
    protected function getSelectStatement(IDataMapper $mapper, int $byFieldPack, int $selectLogic) : ?\PDOStatement
    {
        return null == ($this->selectStatements[get_class($mapper)] ?? null)
            ? null : $this->selectStatements[get_class($mapper)][$byFieldPack] ?? null;
    }

    protected function setSelectStatementFor(
        IDataMapper $mapper,
        int $byFieldPack,
        int $selectLogic,
        \PDOStatement $statement
    ) : void {
        if ( (null == $this->selectStatements[get_class($mapper)] ?? null)
        ) {
            $this->selectStatements[get_class($mapper)] = [];
        }

        $this->selectStatements[get_class($mapper)][$byFieldPack] = $statement;
    }
    #endregion

    #region IBabylonModel construction helpers
    protected function setReadonlyModelValues(IBabylonModel $model, array $values) : void
    {
        $method = new \ReflectionMethod($model, 'setReadonlyValues');
        $method->setAccessible(true);
        $method->invoke($model, $values);
    }

    protected function setReadonlyModelValue(IBabylonModel $model, int $field, $value) : void
    {        
        $method = new \ReflectionMethod($model, 'setReadonlyValue');
        $method->setAccessible(true);
        $method->invoke($model, $field, $value);
    }

    protected function setModelDirty(IBabylonModel $model, bool $dirty) : void
    {
        $property = new \ReflectionProperty($model, 'isDirty');
        $property->setAccessible(true);
        $property->setValue($model, $dirty);
    }

    /**
     * Returns the classname of the IDataMapper implementation that knows how to map persistence for
     * the given IBabylonModel instance.
     * 
     * HACK both IDataMapper and IBabylonModel interfaces are currently implemented by the same class
     * (that is, BabylonModel). So currently this method just returns `get_class($model)` until the
     * larger refactor happens. It's not really needed yet. Hopefully never :P
     */
    protected function getMapperClass(IBabylonModel $model) : string
    {
        return get_class($model);
    }

    /**
     * Instantiate a new IDataMapper implementation that relates to the given model interface.
     * 
     * HACK both IDataMapper and IBabylonModel interfaces are currently implemented by the same class
     * (that is, BabylonModel). So currently this method just returns 
     * `(new $this->getImplementingClass($modelInterface))()` until the larger refactor happens. It's not
     * really needed yet. Hopefully never :P
     */
    protected function getMapperFor(string $modelInterface) : IDataMapper
    {
        //TODO it might be "better code" to be able to make an instance of some DataMapper implementation
        //that contains all the field definitions without creating a model object
        //but for now I'm ok with keeping the implementation of both interfaces in the same class for simplicity
        //There's no performance difference between instantiating a model object vs some 
        //hypothetically-decoupled Mapper object here. It will come down to whether the coupling becomes too
        //cumbersome
        return (new $this->getImplementingClass($modelInterface))();
    }

    /**
     * Returns the classname of the IBabylonModel implementation that relates to the given IDataMapper.
     * 
     * HACK both IDataMapper and IBabylonModel interfaces are currently implemented by the same class
     * (that is, BabylonModel). So currently this method just returns `get_class($mapper)` until the
     * larger refactor happens. It's not really needed yet. Hopefully never :P
     */
    protected function getModelClass(IDataMapper $mapper) : string
    {
        return get_class($mapper);
    }

    protected function withSparkles(IBabylonModel $model) : IBabylonModel
    {
        //make iterator objects for the declared child types of the new Model object
        $iterators = [];
        foreach ( $model->getChildTypes() as $childType ) {
            $iterators[$childType] = new UniqueModelIterator();
        }
        
        //augment $fields with the iterators
        $model->setValue(IDataMapper::F_CHILDREN, $iterators);
        
        //configure the model with DB access
        $model->configureDB($this->pdo, $this->wpdb, $this->tableNamespace);

        //give it the ability to make new Model objects (children etc)
        $model->setModelFactory($this);

        //if the model needs to make VObjects, then set our SabreFacade object to be the VObjectFactory
        if ($model instanceof IVObjectClient) {
            $model->setVObjectFactory($this->sabre);
        }
        
        $this->setModelDirty($model, false); //doesn't need saving, we only just made it

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
            $this->setHasSchema($interface, !$delete);
        }
    }

    protected function hasSchema(string $interface) : bool
    {
        return $this->getOption($this->hasSchemaOption($interface));
    }

    protected function setHasSchema(string $interface, bool $hasSchema) : bool
    {
        //TODO Replace this with something that I can rely on giving useful return value
        //from the docs: "update_option returns true if option value has changed, false if not or if update failed."
        return $this->setOption($this->hasSchemaOption($interface), $hasSchema);
    }

    protected function hasSchemaOption(string $interface) : string
    {
        return ModelFactory::OPT_HAS_SCHEMA_PREFIX 
            . substr($interface, strrpos($interface, "\\") + 1); //chops off the namespace
    }
    #endregion    
}