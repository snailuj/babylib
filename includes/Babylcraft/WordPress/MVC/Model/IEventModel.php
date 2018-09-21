<?php

namespace Babylcraft\WordPress\MVC\Model;

interface IEventModel extends IBabylonModel
{
    const FIELD_NAME  = 0x1;
    const FIELD_RRULE = 0x2;
    const FIELD_START = 0x4;
    const FIELD_UID   = 0x8;

    const EVENT_FIELDS = [
        //RRULE doesn't have a K_NAME, but it IS stored and serialized natch -- through CalDAV, not our normal structure
        self::FIELD_NAME        => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'uri',                              ],
        self::FIELD_RRULE       => [ self::K_TYPE => self::T_STRING,                             self::K_OPTIONAL => true ],
        self::FIELD_START       => [ self::K_TYPE => self::T_DATE,   self::K_NAME  => 'dtstart',                          ],
        self::FIELD_UID         => [ self::K_TYPE => self::T_STRING, self::K_NAME  => 'uid',     self::K_MODE => 'r'      ],
        self::FIELD_CHILD_TYPES => [ self::K_TYPE => self::T_ARRAY,  self::K_VALUE => [ IEventModel::class ]              ]
    ];

    /**
     * Creates a new IEventModel with the given name and recurrence rule, and optional fields to 
     * be set during creation. Adds the new IEventModel as a variation to this event.
     * 
     * @see https://icalendar.org/rrule-tool.html for handy RRULE definition
     * 
     * @param string $name Name of the variation you wish to add, can be any string
     * @param string $rrule Recurrence rule for the variation
     * 
     * @return IEventModel The IEventModel object that represents the variation
     */
    function addVariation(string $name, string $rrule, array $fields = []) : IEventModel;

    function isVariation() : bool;

    function getVariations() : IUniqueModelIterator;
}