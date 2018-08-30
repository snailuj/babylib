<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\VObject\Component\VCalendar;

/**
 * Wraps a Sabre CalDAV PDO Backend so it's more codey and less CalDAV-ey
 * 
 * TODO make an interface in Models for this shit and then inject instances into client code
 * TODO catch PDO exceptions and wrap / transfer them into ModelException (or whatever)
 */
interface ICalendarFactory
{
    function getCalendarsForOwner(string $owner) : array;
    function getCalendarForOwner(string $owner, string $uri) : VCalendar;

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
    function createCalendar(string $owner, string $name, string $tz) : array;
    
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
	function addEvent(
        array $calendarID,
        string $name, 
        \DateTime $dateTimeStart, 
        string $recurrenceRule = '',
        array $exceptionRules = [],
        array $props = []
    ) : VCalendar;
}