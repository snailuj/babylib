<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\VObject\Component\VCalendar;

/**
 * Wraps a Sabre CalDAV Backend so it's more codey and less CalDAV-ey
 */
interface ICalendarModel extends IBabylonModel
{
    const FIELD_OWNER = 0x1;
    const FIELD_URI   = 0x2;
    const FIELD_TZ    = 0x4;

    const CALENDAR_FIELDS = [
        self::FIELD_OWNER       => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'principaluri', self::K_MODE => 'r'    ],
        self::FIELD_URI         => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'uri',          self::K_MODE => 'r'    ],
        self::FIELD_TZ          => [ self::K_TYPE => self::T_DATE,   self::K_NAME  => 'timezone',     self::K_VALUE => 'UTC' ],
        self::FIELD_CHILD_TYPES => [ self::K_TYPE => self::T_ARRAY,  self::K_VALUE => [ IEventModel::class ]                 ]
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
    function addEvent(string $name, string $rrule = '') : IEventModel;
}