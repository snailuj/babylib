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
use Sabre\VObject\Component\VCalendar;

class CalendarModel extends BabylonModel implements ICalendarModel
{
    #region Static
    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false) : string
    {
        return SabreFacade::getSchema($tableNamespace, $wpTableNamespace, $charsetCollate, $drop);
    }

    static public function construct(
        
    )
    #endregion

    #region ICalendarModel implementation
    /**
     * @see ICalendarModel::addEvent()
     */
    public function addEvent(string $name, string $rrule = '', \DateTimeInterface $dtStart) : IEventModel
    {
        return $this->addEventModel(
            $this->getModelFactory()->newEvent($this, $name, $rrule, $dtStart));
    }

    /**
     * @see ICalendarModel::getEvents()
     */
    public function getEvents() : IUniqueModelIterator
    {
        return $this->getChildIterator($this->getEventType());
    }

    protected function loadVCalendar(VObject\Component\VCalendar $vcalendar) : void
    {
        $this->setValue(static::F_TZ, $vcalendar->TZID->getValue());
        $this->setReadonlyValues([
            static::F_URI   => $vcalendar->URI->getValue(),
            static::F_OWNER => $vcalendar->PRINCIPALURI->getValue()
        ]);

        foreach( $vcalendar->VCALENDAR as $vcalendar ) {
            foreach( $vcalendar->VEVENT as $vevent ) {
                $this->loadVEvent($vevent);
            }
        }
    }

    protected function getEventType() : string
    {
        return IEventModel::class;
    }

    protected function addEventModel(IEventModel $eventModel) : IEventModel
    {
        $this->addChild($eventModel->getValue(IEventModel::F_NAME), $eventModel);
        return $eventModel;
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
            $this->veventIterator = new VObject\Recur\EventIterator($this->vcalendar->select("VEVENT"));
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

        \Babylcraft\WordPress\PluginAPI::debugContent(json_encode(@$this->vcalendar->jsonSerialize()), "CalendarModel::doLoadRecord()");

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