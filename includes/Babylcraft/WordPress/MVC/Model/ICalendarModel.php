<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\VObject;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Babylcraft\WordPress\MVC\Model\Sabre\IVObjectClient;

/**
 * Wraps a Sabre VCalendar so it's more codey and less CalDAV-ey and Sabre-ey
 */
interface ICalendarModel extends IBabylonModel, IVObjectClient
{
    const F_OWNER = 0x1;
    const F_URI   = 0x2;
    const F_TZ    = 0x4;

    const CALENDAR_FIELDS = [
        //
        // sometimes I have my doubts about Sabre innit. Riddle me this. They have used a 1-1 relationship in their 
        // schema between calendar and calendarInstance tables (why the fuck isn't it all one table??). So then instead 
        // of recognising that as kludge and taking the hit of refactoring, nah, "let's just use an array for an ID".
        // 
        // Come on guys.
        //
        self::F_ID          => [ self::K_TYPE => self::T_ARRAY,  self::K_VALUE => -1                                     ],
        self::F_OWNER       => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'principaluri', self::K_MODE  => 'r'   ],
        self::F_URI         => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'uri',          self::K_MODE  => 'r'   ],
        self::F_TZ          => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'tzid',         self::K_VALUE => 'UTC' ],
        self::F_CHILD_TYPES => [ self::K_TYPE => self::T_ARRAY,  self::K_VALUE => [ IEventModel::class ]                 ]
    ];
    
    /**
     * Creates a new event with the specified name and recurrence rule (as per iCalendar spec), adds it to this 
     * ICalendarModel and returns it.
     * 
     * @see https://icalendar.org/rrule-tool.html for handy RRULE definition
     * 
     * @param string $name Name of the event
     * @param [string] $rrule Recurrence rule
     * @param \DateTimeInterface $dtStart The start date of the event
     * @param [string] $uid A unique identifier for this event; if not supplied one will be generated during
     * instantiation.
     */
    function addEvent(string $name, string $rrule = '', \DateTimeInterface $dtStart, string $uid = '') : IEventModel;

    /**
     * Returns an iterator for all IEventModel children of this ICalendarModel.
     */
    function getEvents() : IUniqueModelIterator;

    /**
     * Gets a specific event by name
     */
    function getEvent(string $name) : ?IEventModel;

    /**
     * Creates a VCalendar representation of this ICalendarModel
     */
    function asVCalendar() : VCalendar;

    /**
     * Gets a VEvent representation of a specific event by name
     */
    function getEventAsVEvent(string $name) : ?VEvent;
}