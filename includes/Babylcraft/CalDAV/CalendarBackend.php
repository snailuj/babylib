<?php
namespace Babylcraft\CalDAV;

use Sabre\CalDAV;
use Sabre\CalDAV\Backend;
use Sabre\VObject;

/**
 * Wraps a CalDAV PDO Backend so it's more codey and less
 * iCalendary
 * 
 * TODO make an interface in Models for this shit
 */
class CalendarBackend
{
    /**
     * @var \PDO pdo
     */
    // var $pdo;

    /**
     * @var \Sabre\DAV\Server server
     */
    // var $server;

    var $calendarBackend;

    public function __construct(\PDO $pdo, string $tablePrefix, string $baseURI) {
        $this->calendarBackend = $calendarBackend = new Backend\PDO($pdo);

        $calendarBackend->calendarSubscriptionsTableName = $tablePrefix.$calendarBackend->calendarSubscriptionsTableName;
        $calendarBackend->calendarTableName              = $tablePrefix.$calendarBackend->calendarTableName;
        $calendarBackend->calendarInstancesTableName     = $tablePrefix.$calendarBackend->calendarInstancesTableName;
        $calendarBackend->calendarObjectTableName        = $tablePrefix.$calendarBackend->calendarObjectTableName;
        $calendarBackend->calendarChangesTableName       = $tablePrefix.$calendarBackend->calendarChangesTableName;
        $calendarBackend->schedulingObjectTableName      = $tablePrefix.$calendarBackend->schedulingObjectTableName;
    }

    /**
     * Multiple calendars can be specified with differing $names for the same $owner, and multiple
     * $owners can have calendars with the same $name. But $name and $owner must together be unique.
     * 
     * @param string owner  an identifier for the owner of this calendar
     * @param string name   an identifier for this calendar within the owner's namespace
     * @return array    0: calendar id, 1: calendar instance id. Use for calendarID param in addEvent*() fns etc
     */
    public function createCalendar(string $owner, string $name) : array
    {
        return $this->calendarBackend->createCalendar($owner, $name, []);
    }

    public function addEvent(
        array $calendarID,
        string $tz, 
        string $name, 
        \DateTime $dtStart, 
        string $recurrenceRule
    ) : VObject\Component\VCalendar {
        //the RFC dictates everything must be wrapped in a VCalendar Object
        //in CalDAV the VCalendar object also functions as a factory class for other VObjects
        $root = new VObject\Component\VCalendar();
        $root->add($root->add("VTIMEZONE", ["TZID" => $tz]));

        $weekdayFlights = $root->add("VEVENT",
        [
            "SUMMARY" => $name,
            "DTSTART" => $dtStart,
            "RRULE"   => $recurrenceRule
        ]);

        $this->calendarBackend->createCalendarObject($calendarID, $name, $root);

        return $root;
    }
}