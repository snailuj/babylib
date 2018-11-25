<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\Impl\BabylonModel;

use Babylcraft\WordPress\MVC\Model\ModelException;
use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\FieldException;
use Babylcraft\WordPress\MVC\Model\Sabre\SabreFacade;
use Babylcraft\WordPress\MVC\Model\IUniqueModelIterator;
use Babylcraft\WordPress\MVC\Model\Sabre\VObjectUtil;
use Babylcraft\WordPress\MVC\Model\Sabre\IVObjectFactory;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Node;

class CalendarModel extends BabylonModel implements ICalendarModel
{
    /**
     * @var VObjectFactory used for creating Sabre\VObject\Recur recurrence-rule checking
     */
    protected $vobjectFactory;

    /**
     * @var VCalender Cached copy of the VCalender representation
     */
    protected $vcalendar;

    #region Static
    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false) : string
    {
        return SabreFacade::getSchema($tableNamespace, $wpTableNamespace, $charsetCollate, $drop);
    }

    static public function construct(string $owner, string $uri, string $tz = 'UTC') : ICalendarModel
    {
        $new = new static();
        $new->setReadonlyValues([
            ICalendarModel::F_OWNER => $owner,
            ICalendarModel::F_URI   => $uri
        ]);

        $new->setValue(ICalendarModel::F_TZ, $tz);

        return $new;
    }
    #endregion

    #region ICalendarModel implementation
    /**
     * @see ICalendarModel::addEvent()
     */
    public function addEvent(
        string $name,
        string $rrule = '',
        \DateTimeInterface $dtStart,
        string $uid = ''
    ) : IEventModel
    {
        return $this->addEventModel(
            $this->getModelFactory()->newEvent(
                $this,
                $name,
                $rrule,
                $dtStart,
                $uid
            )
        );
    }

    /**
     * @see ICalendarModel::getEvents()
     */
    public function getEvents() : IUniqueModelIterator
    {
        return $this->getChildIterator($this->getEventType());
    }

    public function getEvent(string $name) : ?IEventModel
    {
        return $this->getEvents()[$name] ?? null;
    }

    public function setVObjectFactory(IVObjectFactory $factory)
    {
        $this->vobjectFactory = $factory;
    }

    protected function getEventType() : string
    {
        return IEventModel::class;
    }

    public function asVObject() : Node
    {
        if (!$this->vcalendar) { //set null in doSetValue() override
            $this->vcalendar = $this->vobjectFactory->getAsVCalendar($this);
        }

        return $this->vcalendar;
    }

    protected function addEventModel(IEventModel $eventModel) : IEventModel
    {
        $this->addChild($this->getChildKey($eventModel), $eventModel);
        $this->vobjectFactory->copyToVCalendar($eventModel, $this->asVCalendar());
        return $eventModel;
    }

    public function asVCalendar() : VCalendar
    {
        return $this->asVObject();
    }

    public function getEventAsVObject(string $name) : ?Node
    {
        $test = $this->asVCalendar()->searchByURI($name);
        return $test;

        // $model = null;
        // foreach ($this->getEvents() as $eventName => $event) {
        //     if ($eventName === $name) {
        //         return $this->asVCalendar()->searchByURI($event->getValue(IEventModel::F_NAME));
        //     }

        //     foreach ($event->getVariations() as $variationName => $variation) {
        //         if ($variationName === $name) {
        //             $parentNode = $this->asVCalendar()->getByUID($event->getValue(IEventModel::F_UID));
        //             foreach ($parentNode->select('EXRULE') as $recur) {
        //                 if ($name === $recur->offsetGet($variation->getFieldName(IEventModel::F_UID))->getValue()) {
        //                     return $recur;
        //                 }
        //             }
        //         }
        //     }
        // }

        // return null;
    }

    public function getEventsByDate(\DateTimeInterface $date) : array
    {
        $events = [];
        foreach ($this->getEvents() as $eventName => $event) {
            if ($event->isInTimeRange($date, \DateInterval::createFromDateString('1 days'))) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @var VObject\Recur\EventIterator The Sabre class that handles all the recurrence checking
     */
    // private $veventIterator;
    // protected function getVEventIterator() : VObject\Recur\EventIterator
    // {
    //     if (!$this->veventIterator) {
    //         $this->veventIterator = new VObject\Recur\EventIterator($this->vcalendar->select("VEVENT"));
    //     }

    //     return $veventIterator;
    // }
    #endregion

    #region overrides
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
        $this->addFields(static::CALENDAR_FIELDS);
    }

    protected function getChildKey(IBabylonModel $child)
    {
        if ($child instanceof IEventModel) {
            return $child->getValue(IEventModel::F_NAME);
        }

        throw new FieldException(FieldException::ERR_WRONG_TYPE, get_class($child) . " is not a valid child of CalendarModel");
    }

    protected function doSetValue(int $field, $value) : void
    {
        parent::doSetValue($field, $value);
        $this->vcalendar = null;
    }
    #endregion
}