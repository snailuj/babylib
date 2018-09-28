<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Sabre\CalDAV\Backend;
use Sabre\VObject;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\SabreFacade;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;


class EventModel extends BabylonModel implements IEventModel
{
    /**
     * @var SabreFacade Object that makes the Sabre API more codey and 
     * less iCalendary
     */
    private $sabre;

    /**
     * @var array List of IEventModel objects whose F_RRULE and other properties define
     * variations to the F_RRULE of this parent IEventModel
     */
    //protected $variations = [];

    #region static
    /**
     * @see IModelFactory::event()
     */
    static public function createEvent(ICalendarModel $calendar, string $name, string $rrule, \DateTime $start) : IEventModel
    {
        return static::makeEvent($calendar, $name, $rrule, $start);
    }

    /**
     * @see IModelFactory::eventVariation()
     */
    static public function createVariation(IEventModel $event, string $name, string $rrule) : IEventModel
    {
        return static::makeEvent($event, $name, $rrule, null);
    }

    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false) : string
    {
        return "";
    }

    //if $uid is null, assumes we are creating a variation
    static protected function makeEvent(IBabylonModel $parent, string $name, string $rrule, \DateTime $start = null) : IEventModel {
        $fields = [
            static::F_NAME   => $name,
            static::F_RRULE  => $rrule,
            static::F_START  => $start,
            static::F_PARENT => $parent
        ];

        $event = new static();

        if (!$start) {
            $event->isVariation = true;
            $event->setReadOnlyValue(static::F_UID, \Babylcraft\Util::generateUid());
        }

        $event->setParentType(get_class($parent));
        $event->setValues($fields);

        return $event;
    }
    #endregion

    #region IEventModel Implementation
    /**
     * @var bool
     */
    private $isVariation = false;
    public function addVariation(string $name, string $rrule, array $fields = []) : IEventModel
    {
        return $this->addVariationModel($this->getModelFactory()->eventVariation($this, $name, $rrule, $fields));
    }

    public function isVariation() : bool
    {
        return $this->isVariation;
    }

    public function getVariations() : IUniqueModelIterator
    {
        return $this->getChildIterator($this->getVariationType());
    }

    protected function getVariationType() : string 
    {
        return IEventModel::class;
    }

    protected function addVariationModel(IEventModel $variation) : IEventModel
    {
        $this->addChild($variation->getValue(static::F_NAME), $variation);

        return $variation;
    }

    protected function eventToCalDAV() : array
    {
        $caldav = [
            "SUMMARY" => $this->getValue(static::F_NAME),
            "DTSTART" => $this->getValue(static::F_START)
        ];

        $rrule = $this->getValue(static::F_RRULE);
        if ($rrule) {
            $caldav["RRULE"] = $rrule;
        }

        $caldav = array_merge($caldav, $this->propsToCalDAV());

        \Babylcraft\WordPress\PluginAPI::debugContent($caldav, "eventToCalDAV() generated");

        return $caldav;
    }

    protected function propsToCalDAV() : array
    {
        return []; //EventModel doesn't actually have any non-standard props at this stage
    }

    protected function variationsToCalDAV() : array
    {
        $caldav = [];
        foreach( $this->getVariations() as $variation ) {
            $caldav[] = $this->variationToCalDAV($variation);
        }

        \Babylcraft\WordPress\PluginAPI::debugContent($caldav, "variationsToCalDAV() generated");

        return $caldav;
    }

    protected function variationToCalDAV(IEventModel $variation) : array
    {
        return [
            "EXRULE"   => $variation->getValue(static::F_RRULE),
            "children" => $variation->propsToCalDAV()
        ];
    }
    #endregion

    #region IBabylonModel Implementation
    public /* override */ function configureDB(\PDO $pdo, \wpdb $wpdb, string $tableNamespace = 'babyl_', string $wpTableNamespace = 'wp_')
    {
        parent::configureDB($pdo, $wpdb, $tableNamespace, $wpTableNamespace);
        $this->sabre = new SabreFacade($pdo, $tableNamespace);
    }

    protected /* override */ function setupFields() : void
    {
        parent::setupFields();
        $this->addFields(static::EVENT_FIELDS);
        $this->setParentType(ICalendarModel::class);
        $this->setFieldType(static::F_ID, static::T_STRING); //override F_ID to string (because it's a UID)

        //
        // this line removes ID from persistence / serialize calls -- CalDAV and client-side code both rely
        // on the URI to identify events
        //
        unset($this->fields[static::F_ID][static::K_NAME]);
    }

    protected /* override */ function doGetValue(int $field)
    {
        if ($field === static::F_ID) {
            if ($this->isVariation()) {
                return $this->getValue(static::F_UID) ?? -1;
            }

            return $this->fields[static::F_ID][static::K_VALUE];
        }

        return null;
    }

    protected function doCreateRecord() : bool
    {
        static::createRecordFor($this);

        return true;
    }

    static protected function createRecordFor(IEventModel $event) : void
    {
        $event->sabre->createEvent(
            $event->getValue(static::F_PARENT)->getValue(static::F_ID),
            $event->getValue(static::F_NAME),
            $event->eventToCalDAV(),
            $event->variationsToCalDAV()
        );
    }

    protected function doUpdateRecord() : bool
    {
        \Babylcraft\WordPress\PluginAPI::debug("doUpdateRecord() for: ". $this->getValue(static::F_NAME));
        return true;
        // throw new \Exception("Not implemented yet!");
    }
    #endregion
}