<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Sabre\CalDAV\Backend;
use Sabre\VObject;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\SabreFacade;


class EventModel extends BabylonModel implements IEventModel
{
    /**
     * @var SabreFacade Object that makes the Sabre API more codey and 
     * less iCalendary
     */
    private $sabre;

    /**
     * @var array List of IEventModel objects whose FIELD_RRULE and other properties define
     * variations to the FIELD_RRULE of this parent IEventModel
     */
    private $variations = [];

    #region static
    /**
     * @see IModelFactory::event()
     */
    static public function event(ICalendarModel $calendar, string $name, string $rrule, \DateTime $start) : IEventModel
    {
        $event = static::makeEvent($name, $rrule);
        BabylonModel::setParent($calendar, $event);

        return $event;
    }

    /**
     * @see IModelFactory::eventVariation()
     */
    static public function createVariation(IEventModel $event, string $name, string $rrule) : IEventModel
    {
        $variation = static::makeEvent($name, $rrule);
        $variation->setType(IEventModel::FIELD_PARENT, EventModel::class);
        BabylonModel::setParent($event, $variation);

        return $variation;
    }

    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false) : string
    {
        return "";
    }

    static private function makeEvent(string $name, string $rrule, \DateTime $start = null) : IEventModel
    {
        $event = new EventModel();
        $event->setValues([
            IEventModel::FIELD_NAME  => $name,
            IEventModel::FIELD_RRULE => $rrule,
            IEventModel::FIELD_UID   => \Babylcraft\Util::generateUid()
        ]);

        if ($start) {
            $event->setValue(IEventModel::FIELD_START, $start);
        }

        return $event;
    }
    #endregion

    #region IEventModel Implementation
    public function variation(string $name, string $rrule, array $fields = []) : IEventModel
    {
        if (isset($this->variations[$name])) {
            throw new FieldException(FieldException::ERR_UNIQUE_VIOLATION, $name);
        }

        return $this->variations[$name] = $this->getModelFactory()->eventVariation($this, $name, $rrule, $fields);
    }

    protected function eventToCalDAV() : array
    {
        $caldav = [
            "SUMMARY" => $this->getValue(IEventModel::FIELD_NAME),
            "DTSTART" => $this->getValue(IEventModel::FIELD_START)
        ];

        $rrule = $this->getValue(IEventModel::FIELD_RRULE);
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
        foreach( $this->variations as $variation ) {
            $caldav[] = $this->variationToCalDAV($variation);
        }

        \Babylcraft\WordPress\PluginAPI::debugContent($caldav, "variationsToCalDAV() generated");

        return $caldav;
    }

    protected function variationToCalDAV(IEventModel $variation) : array
    {
        $rrule = $variation->getValue(IEventModel::FIELD_RRULE);
        if (!$rrule) {
            throw new FieldException(FieldException::ERR_FIELD_IS_NULL, "RRULE");
        }

        $caldav = [
            "EXRULE" => $rrule,
            "X-UID"  => $variation->getValue(IEventModel::FIELD_UID),
            "X-NAME" => $variation->getValue(IEventModel::FIELD_NAME)
        ];

        $caldav["children"] = $variation->propsToCalDAV();
    }
    #endregion

    #region IBabylonModel Implementation
    public /* override */ function configureDB(\PDO $pdo, \wpdb $wpdb, string $tableNamespace = 'babyl_', string $wpTableNamespace = 'wp_')
    {
        parent::configureDB($pdo, $wpdb, $tableNamespace, $wpTableNamespace);
        $this->sabre = new SabreFacade($pdo, $tableNamespace);
    }

    protected function setupFields() : void
    {
        parent::setupFields();
        $this->addFields(self::EVENT_FIELDS);
        $this->setType(self::FIELD_PARENT, ICalendarModel::class);
        $this->setType(self::FIELD_ID, self::T_STRING); //override the type of FIELD_ID to string
    }

    protected /* override */ function doGetValue(int $field)
    {
        if ($field === IEventModel::FIELD_ID) {
            return $this->fields[IEventModel::FIELD_UID][IEventModel::K_VALUE] ?? -1;
        }
    }

    protected function doCreateRecord() : bool
    {
        $this->sabre->createEvent(
            $this->getValue(self::FIELD_PARENT)->getValue(ICalendarModel::FIELD_ID),
            $this->getValue(self::FIELD_NAME),
            $this->eventToCalDAV(),
            $this->variationsToCalDAV()
        );

        return true;
    }

    protected function doUpdateRecord() : bool
    {
        throw new \Exception("Not implemented yet!");
    }
    #endregion
}