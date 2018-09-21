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
    static public function createCalendar(string $owner, string $uri) : ICalendarModel
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
    // protected $events = [];
    
    public function addEvent(string $name, string $rrule = '') : IEventModel
    {
        return $this->addEventModel(
            $this->getModelFactory()->event($this, $name, $rrule));
    }

    protected function addEventModel(IEventModel $eventModel) : IEventModel
    {
        // if (array_key_exists($name, $this->events)) {
        //     throw new FieldException(FieldException::ERR_UNIQUE_VIOLATION, $name);
        // }

        //BabylonModel::setParent($this, $eventModel);
        //$this->events[$name] = $eventModel;
        $this->addChild($eventModel->getValue(IEventModel::FIELD_NAME), $eventModel);

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
        $this->vcalendar = $this->sabre->getCalendarForOwner(
            $this->getValue(ICalendarModel::FIELD_OWNER),
            $this->getValue(ICalendarModel::FIELD_URI)
        );

        return true;
    }

    protected function doCreateRecord(): bool
    {
        $this->calendarId = $this->sabre->createCalendar(
            $this->getValue(ICalendarModel::FIELD_OWNER),
            $this->getValue(ICalendarModel::FIELD_URI)
        );

        return true;
    }

    protected function doUpdateRecord(): bool
    {
        \Babylcraft\WordPress\PluginAPI::debug(
            "doUpdateRecord() for: ".
            $this->getValue(static::FIELD_OWNER) ." ".
            $this->getValue(static::FIELD_URI)
        );

        return true;
        //todo perftest this, it's O(2n) for events because of the duplicated loop in isDirty()
        //consider adding a doIfDirty() function to BabylonModel
        // if ($this->isDirty()) {
        //     foreach( $this->getChildren(IEventModel::class) as $event ) {
        //         $event->save();
        //     }
        // }

        // return true;
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