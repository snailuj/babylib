<?php

namespace Babylcraft\GraphQL\TypeDef;

use Babylcraft\GraphQL\TypeDef\Calendar\VCalendarType;

/**
 * Type registry for all common Babylon GraphQL types.
 * Static because we don't need or want multiple copies
 * of these Type definitions being created.
 */
class BabylonTypes {

    /**
     * Returns an instance of VCalendarType
     */
    public static function vcalendar() : VCalendarType {
        return self::$vcalendar ? : ( self::$vcalendar = new VCalendarType() );
    }

    private static $vcalendar;
}