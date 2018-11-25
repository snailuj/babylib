<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\VObject;
use Babylcraft\WordPress\MVC\Model\Sabre\IVObjectClient;
use Sabre\VObject\Node;


interface IEventModel extends IBabylonModel, IVObjectClient
{
    const F_NAME         = 0x1;
    const F_RRULE        = 0x2;
    const F_START        = 0x4;

    const EVENT_FIELDS = [
        self::F_NAME         => [ self::K_TYPE => self::T_STRING,            self::K_NAME  => 'uri',            self::K_MODE => 'r'      ],
        self::F_RRULE        => [ self::K_TYPE => self::T_STRING,            self::K_NAME  => 'rrule',          self::K_OPTIONAL => true ],
        self::F_START        => [ self::K_TYPE => \DateTimeImmutable::class, self::K_NAME  => 'dtstart',        self::K_OPTIONAL => true ],
        self::F_CHILD_TYPES  => [ self::K_TYPE => self::T_ARRAY,             self::K_VALUE => [ IEventModel::class ]                     ]
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
    function addVariation(string $name, string $rrule) : IEventModel;

    /**
     * Returns true if this model represents a variation, false if it represents an event.
     */
    function isVariation() : bool;

    /**
     * Returns all variations keyed by name.
     */
    function getVariations() : IUniqueModelIterator;

    /**
     * Returns a variation by its name.
     */
    function getVariation(string $name) : ?IEventModel;

    /**
     * Returns true if this event falls within the given date range, false if not.
     */
    function isInTimerange(\DateTimeInterface $dateTime, \DateInterval $interval) : bool;
}