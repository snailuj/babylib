<?php

namespace Babylcraft\GraphQL\TypeDef\Calendar;

use GraphQL\Type\Definition\Type;
use Babylcraft\GraphQL\TypeDef\BabylonType;

/**
 * Represents a VCalendar object from the iCalendar Spec.
 * 
 * Just the basics of what we need to get Events into ical.js.
 */
class VCalendarType extends BabylonType
{
    private const TYPE_NAME = "VCalendarType";
    private const TYPE_DESC = "Container for Calendar Information";

    static protected function getName() : string { return self::TYPE_NAME; }
    static protected function getDescription() : string { return self::TYPE_DESC; }
    static protected function getFieldDefs(): array
    {
        return [
            'uri' => static::makeFieldDef(
                Type::string(), 
                'URI for the Calendar. If you know it, you can use it to query for a particular Calendar.'
            ),
            'principalURI' => static::makeFieldDef(
                Type::string(), 
                'URI for the Calendars owner. Can be used to query for all Calendars belonging to a particular user.'
            ),
            'data' => static::makeFieldDef(
                Type::string(),
                'JSON string representation of the Calendar and any children'
            )
        ];
    }
}