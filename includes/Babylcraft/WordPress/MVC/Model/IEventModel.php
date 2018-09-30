<?php

namespace Babylcraft\WordPress\MVC\Model;

interface IEventModel extends IBabylonModel
{
    const F_NAME  = 0x1;
    const F_RRULE = 0x2;
    const F_START = 0x4;
    const F_UID   = 0x8;

    const EVENT_FIELDS = [
        //RRULE doesn't have a K_NAME, but it IS stored and serialized natch -- through CalDAV, not our normal structure
        self::F_NAME        => [ self::K_TYPE => self::T_STRING,            self::K_NAME  => 'uri',                              ],
        self::F_RRULE       => [ self::K_TYPE => self::T_STRING,                                        self::K_OPTIONAL => true ],
        self::F_START       => [ self::K_TYPE => \DateTimeImmutable::class, self::K_NAME  => 'dtstart', self::K_OPTIONAL => true ],
        self::F_UID         => [ self::K_TYPE => self::T_STRING,            self::K_NAME  => 'uid',     self::K_MODE => 'r'      ],
        self::F_CHILD_TYPES => [ self::K_TYPE => self::T_ARRAY,             self::K_VALUE => [ IEventModel::class ]              ]
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

    function addVariationModel(IEventModel $model) : IEventModel;

    function isVariation() : bool;

    function getVariations() : IUniqueModelIterator;
}