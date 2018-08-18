<?php
namespace Babylcraft\CalDAV;

use Sabre\CalDAV;
use Sabre\CalDAV\Backend;
use Sabre\VObject;

/**
 * Wraps a Sabre CalDAV PDO Backend so it's more codey and less CalDAV-ey
 * 
 * TODO make an interface in Models for this shit and then inject instances into client code
 * TODO catch PDO exceptions and wrap / transfer them into ModelException (or whatever)
 */
class CalendarBackend
{
    var $calendarBackend;

    /**
     * @param \PDO   $pdo           A connection to a database for storing / retrieving calendar data
     * @param string $tablePrefix   Will be prepended to all table names when querying / updating the database
     */
    public function __construct(\PDO $pdo, string $tablePrefix = null, string $baseURI = '/') {
        $this->calendarBackend = $calendarBackend = new Backend\PDO($pdo);

        $calendarBackend->calendarSubscriptionsTableName = $tablePrefix."babyl_".$calendarBackend->calendarSubscriptionsTableName;
        $calendarBackend->calendarTableName              = $tablePrefix."babyl_".$calendarBackend->calendarTableName;
        $calendarBackend->calendarInstancesTableName     = $tablePrefix."babyl_".$calendarBackend->calendarInstancesTableName;
        $calendarBackend->calendarObjectTableName        = $tablePrefix."babyl_".$calendarBackend->calendarObjectTableName;
        $calendarBackend->calendarChangesTableName       = $tablePrefix."babyl_".$calendarBackend->calendarChangesTableName;
        $calendarBackend->schedulingObjectTableName      = $tablePrefix."babyl_".$calendarBackend->schedulingObjectTableName;
    }

    /**
     * Multiple calendars can be specified with differing $names for the same $owner, and multiple
     * $owners can have calendars with the same $name. But $name and $owner must together be unique.
     * 
     * @param string $owner  An identifier for the owner of this calendar
     * @param string $name   An identifier for this calendar within the owner's namespace
     * @param string $tz     The timezone, in <a href="https://en.wikipedia.org/wiki/Tz_database">Olson ID</a> format
     * 
     * @return array         0: calendar id, 1: calendar instance id. Use for calendarID param in ::addEvent() fn
     */
    public function createCalendar(string $owner, string $name, string $tz) : array
    {
        $timezonePropName = array_search("timezone", $this->calendarBackend->propertyMap);
        $calendarId = $this->calendarBackend->createCalendar($owner, $name, [
            $timezonePropName => $tz
        ]);

        return $calendarId;
    }

    /**
     * Adds an event with the given properties to the calender identified by the given array-based calendar ID.
     *  
     * @param array     $calendarID       An array of the form returned by ::createCalendar()
     * @param string    $name             The name of the event, this will be saved as its URI
     * @param \DateTime $dateTimeStart    The start date and time of the event
     * @param string    $recurrenceRule   Recurrence rule as in iCalendar Spec. <a href="https://icalendar.org/rrule-tool.html">Generate one here</a>.
     * @param array     $recurrenceParams List of RRULE parameters. The spec states that Non-Standard parameters should begin with "X-", but this is 
     *                                    not enforced by any iCalendar or jCal parser / generator that I have tested. Nor is it enforced here. 
     *                                    Format $recurrenceParams as an associative array, e.g. 
     *                                      <code>$recurrenceParams = ["X-COFFEE-ORDER" => "No frappacinos!", "X-TOPIC" => "Dependent Origination"]</code>
     * @param array     $exceptionRules   List of exceptions (specified as RRULES) to $recurrenceRule. You can add Non-Standard parameters to your EXRULE(s)
     *                                    same as for $recurrenceParams above.
     * 
     *                                    Format like the following.
     * <pre>
     * [
     *      <uid> => [
     *        "EXRULE" => <exrule definition 1>,
     *        "X-"<property name1> => <property value1>
     *    ]
     *      <uid> => [
     *        "EXRULE" => <exrule definition 2>,
     *        "X-"<property name>  => <property value1>
     *        "X-"<property name2> => <property value2>
     *    ]
     *   ...
     * ]
     * </pre>
     * 
     * The <uid> will be added as "X-UID" prop to the EXRULE (other "X-" properties are optional, follow the same format as for @param $props
     * if you want to use).
     * 
     * For example:
     * 
     * <pre>
     * $exceptionRules = [
     *      "{6CCEC704-826D-B7F0-330B-F096D7B7339E}" => [
     *          "EXRULE"         => "FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR"
     *          "X-COFFEE_ORDER" => "I can haz frappacino",
     *          "X-TOPIC"        => "Boss away, kittehs play"
     *      ]
     * ]
     * </pre>
     * Doesn't support EXDATE as of yet. Just use a EXRULE that limits to a singular date.
     * 
     * @return Sabre\VObject\Component\VCalendar    iCalendar dictates that every object must be wrapped in a VCalendar. addEvent()->VEVENT will
     *                                              hold the actual event.
     */
    public function addEvent(
        array $calendarID,
        string $name, 
        \DateTime $dateTimeStart, 
        string $recurrenceRule = '',
        array $exceptionRules = [],
        array $props = []
    ) : VObject\Component\VCalendar 
    {
        $root = new VObject\Component\VCalendar([
                "VEVENT" => [
                    "SUMMARY" => $name,
                    "DTSTART" => $dateTimeStart,
                ]
            ],
            $defaults = false);

        //add RRULE
        if ($recurrenceRule) {
            $root->VEVENT->add("RRULE", $recurrenceRule);
            foreach ($props as $propName => $propValue) {
                $root->VEVENT->RRULE->add($propName, $propValue);
            }
        }

        //add EXRULEs
        foreach ($exceptionRules as $uid => $definition) {
            if ($exrule = $definition["EXRULE"]) {
                unset($definition["EXRULE"]); //remove it from definition so we can use the rest as children
                $definition["X-UID"] = $uid;
                $root->VEVENT->add("EXRULE", $exrule, $children = $definition);
            }
        }

        $this->calendarBackend->createCalendarObject($calendarID, $name, $root->serialize());

        return $root;
    }

    public function getCalendarsForOwner(string $owner) : array
    {
        $calendars = [];
        foreach ( $this->calendarBackend->getCalendarsForUser($owner) as $calendarInfo ) {
            $root = new VObject\Component\VCalendar($calendarInfo, false);

            $objects = $this->calendarBackend->getCalendarObjects($calendarInfo['id']);

            //
            // The following properties generate warnings from deep inside sabre/vobject on serialization
            // '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set'
            //  and
            // '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'
            //
            //  I have no clue why, or what those properties are for. They come out as empty from the
            //  serializing process, but I have decided to swallow the warnings via '@' on the call to jsonSerialize() 
            //  and assume that's expected behaviour (even if nasty). So I can continue with my project and not 
            //  fix bugs on CalDAV.
            //  
            //  May have to revisit this if those props are turn out crucial to some iCalendar clients.
            //
            //  Uncomment the following lines to prevent the PHP warnings
            //
                  //unset($calendarInfo['{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set']);
                  //unset($calendarInfo['{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'        ]);
            //  
            //  Rather than solving the problem, that just means the props will be removed from the output
            //  entirely instead of being set to empty string as is currently the case.
            //
            $objectURIs = array_column($objects, 'uri');
            $calendarObjects = $this->calendarBackend->getMultipleCalendarObjects($calendarInfo['id'], $objectURIs);
            foreach ( $calendarObjects as $calendarObject ) {
                $object = VObject\Reader::read($calendarObject['calendardata']);
                $root->add($object);
            }

            $calendars[$calendarInfo['uri']] = $root;
        }

        return $calendars;
    }
}