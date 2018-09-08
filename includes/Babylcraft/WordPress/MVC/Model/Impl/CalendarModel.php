<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\Impl\BabylonModel;

use Sabre\VObject;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Sabre\CalDAV\Backend;
use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\SabreFacade;

class CalendarModel extends BabylonModel implements ICalendarModel
{
    #region Static
    static public function calendar(string $owner, string $uri) : ICalendarModel
    {
        $cal = new CalendarModel();
        $cal->setValues([
            ICalendarModel::FIELD_OWNER => $owner,
            ICalendarModel::FIELD_URI   => $name
        ]);

        return $cal;
    }

    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false) : string
    {
        return SabreFacade::getSchema($tableNamespace, $wpTableNamespace, $charsetCollate, $drop);
    }
    #endregion

    #region ICalendarModel implementation
    /**
     * @var SabreFacade Object that makes the Sabre API more codey and 
     * less iCalendary
     */
    private $sabre;

    /**
     * @var Sabre\VObject\VCalendar Backing object that stores data in a format
     * that can be saved using CalDAV
     */
    protected $vcalendar;

    /**
     * @var array The calendar DB ID as CalDAV understands it
     */
    private $calendarId = -1;

    /**
     * @var array The events defined on this Calendar
     */
    protected $events = [];
    
    public function event(string $name, string $rrule = '') : IEventModel
    {
        return $this->eventModel(
            $this->getModelFactory()->event($this, $name, $rrule), $name);
    }

    protected function eventModel(IEventModel $eventModel, string $name) : IEventModel
    {
        if (array_key_exists($name, $this->events)) {
            throw new FieldException(FieldException::ERR_UNIQUE_VIOLATION, $name);
        }

        $eventModel->setType(self::FIELD_PARENT, get_class($eventModel));
        BabylonModel::setParent($this, $eventModel);
        $this->events[$name] = $eventModel;

        return $eventModel;
    }
    #endregion

    #region IBabylonModel implementation
    public function configureDB(
        \PDO $pdo,
        \wpdb $wpdb,
        string $tableNamespace = 'babyl_',
        string $wpTableNamespace = 'wp_'
    ) {
        parent::configureDB($pdo, $wpdb, $tableNamespace, $wpTableNamespace);
        $this->sabre = new SabreFacade($pdo, $tableNamespace);
    }

    protected function setupFields() : void
    {
        parent::setupFields();
        $this->addFields(self::CALENDAR_FIELDS);
    }

    protected function doLoadRecord() : bool
    {
        $this->vcalendar = $sabre->getCalendarForOwner(
            $this->getValue(ICalendarModel::FIELD_OWNER),
            $this->getValue(ICalendarModel::FIELD_URI)
        );

        return true;
    }

    protected function doCreateRecord(): bool
    {
        $uri = $this->getValue(ICalendarModel::FIELD_URI);
        $owner = $this->getValue(ICalendarModel::FIELD_OWNER);
        $this->calendarId = $this->sabre->createCalendar($owner, $uri);

        foreach( $this->events as $eventName => $event ) {
            $event->save();
        }

        $this->vcalendar = $this->sabre->getCalendarForOwner($owner, $uri);
        \Babylcraft\WordPress\PluginAPI::debug(json_encode(@$this->vcalendar->jsonSerialize()));

        return true;
    }

    protected function doUpdateRecord(): bool
    {
        //todo perftest this, it's O(2n) for events because of the duplicated loop in isDirty()
        //consider adding a doIfDirty() function to BabylonModel
        if ($this->isDirty()) {
            \Babylcraft\WordPress\PluginAPI::warn("Saving calendar, timezone changes are not supported.");
            foreach( $this->events as $eventName => $event ) {
                $event->save();
            }
        }

        return true;
    }

    protected function isDirty() : bool
    {
        if ($this->dirty) {
            return true;
        }

        foreach( $this->events as $event ) {
            if ($event->isDirty()) {
                return true;
            }
        }

        return false;
    }

    protected function doGetValue(int $field)
    {
        if ($field === IBabylonModel::FIELD_ID) {
            if ($this->calendarId === -1 && $this->vcalendar) {
                $this->calendarId = explode(',', $this->vcalendar->ID);
            }

            return $this->calendarId;
        }

        return null; //fallback to default getValue in parent class
    }
    #endregion
}