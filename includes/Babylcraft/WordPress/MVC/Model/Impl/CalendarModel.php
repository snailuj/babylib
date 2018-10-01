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
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;

class CalendarModel extends BabylonModel implements ICalendarModel
{
    #region Static
    static public function createCalendar(string $owner, string $uri) : ICalendarModel
    {
        $cal = new CalendarModel();
        $cal->setValues([
            ICalendarModel::F_OWNER => $owner,
            ICalendarModel::F_URI   => $name
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
     * @see ICalendarModel::addEvent()
     */
    public function addEvent(string $name, string $rrule = '', \DateTimeInterface $dtStart) : IEventModel
    {
        return $this->addEventModel(
            $this->getModelFactory()->event($this, $name, $rrule, $dtStart));
    }

    /**
     * @see ICalendarModel::getEvents()
     */
    public function getEvents() : IUniqueModelIterator
    {
        return $this->getChildIterator($this->getEventType());
    }

    public function toVCalendar(): VObject\Component\VCalendar
    {
        if (!$this->vcalendar) {
            $this->vcalendar = new VObject\Component\VCalendar($this->calendarToCalDAV());
        }

        return $this->vcalendar;
    }

    protected function getEventType() : string
    {
        return IEventModel::class;
    }

    protected function addEventModel(IEventModel $eventModel) : IEventModel
    {
        $this->addChild($eventModel->getValue(IEventModel::F_NAME), $eventModel);
        $this->toVCalendar()->add($eventModel->toVEvent());
        //unset the iterator because we have added a new period that may change the results
        $this->veventIterator = null;

        return $eventModel;
    }

    protected function loadVCalendar(VObject\Component\VCalendar $vcalendar) : void
    {
        $this->vcalendar = $vcalendar;
        $this->setValue(static::F_TZ, $vcalendar->TZID->getValue());
        foreach( $vcalendar->VCALENDAR as $vcalendar ) {
            foreach( $vcalendar->VEVENT as $vevent ) {
                $this->loadVEvent($vevent);
            }
        }
    }

    protected function loadVEvent(VObject\Component\VEvent $vevent) : void
    {
        $this->addEvent($this->getModelFactory()->eventFromVEvent($this, $vevent));
    }

    /**
     * @var VObject\Recur\EventIterator The Sabre class that handles all the recurrence checking
     */
    private $veventIterator;
    protected function getVEventIterator() : VObject\Recur\EventIterator
    {
        if (!$this->veventIterator) {
            $this->veventIterator = new VObject\Recur\EventIterator($this->toVCalendar()->select("VEVENT"));
        }

        return $veventIterator;
    }

    protected function calendarToCalDAV() : array
    {
        return [
            "URI"          => $this->getValue(static::F_URI),
            "PRINCIPALURI" => $this->getValue(static::F_OWNER)
        ];
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
        $this->loadVCalendar(
            $this->sabre->getCalendarForOwner(
                $this->getValue(ICalendarModel::F_OWNER),
                $this->getValue(ICalendarModel::F_URI)
            )
        );

        \Babylcraft\WordPress\PluginAPI::debugContent(json_encode(@$this->toVCalendar()->jsonSerialize()), "CalendarModel::doLoadRecord()");

        return true;
    }

    protected function doCreateRecord(): bool
    {
        $this->calendarId = $this->sabre->createCalendar(
            $this->getValue(ICalendarModel::F_OWNER),
            $this->getValue(ICalendarModel::F_URI)
        );

        return true;
    }

    protected function doUpdateRecord(): bool
    {
        \Babylcraft\WordPress\PluginAPI::debug(
            "doUpdateRecord() for: ".
            $this->getValue(static::F_OWNER) ." ".
            $this->getValue(static::F_URI)
        );

        return true;
    }

    protected function doGetValue(int $field)
    {
        if ($field === IBabylonModel::F_ID) {
            if ($this->calendarId === -1 && $this->vcalendar) {
                $this->calendarId = explode(',', $this->vcalendar->ID);
            }

            return $this->calendarId;
        }

        return null; //fallback to default getValue in parent class
    }
    #endregion
}