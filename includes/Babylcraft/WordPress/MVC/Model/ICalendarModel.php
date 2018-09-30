<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

/**
 * Wraps a Sabre CalDAV Backend so it's more codey and less CalDAV-ey
 */
interface ICalendarModel extends IBabylonModel
{
    const F_OWNER = 0x1;
    const F_URI   = 0x2;
    const F_TZ    = 0x4;

    const CALENDAR_FIELDS = [
        self::F_OWNER       => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'principaluri', self::K_MODE => 'r'    ],
        self::F_URI         => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'uri',          self::K_MODE => 'r'    ],
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
     */
    function addEvent(string $name, string $rrule = '', \DateTimeInterface $dtStart) : IEventModel;

    /**
     * Returns an iterator for all IEventModel children of this ICalendarModel.
     */
    function getEvents() : IUniqueModelIterator;
}