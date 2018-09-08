<?php

namespace Babylcraft\WordPress\MVC\Model;

/**
 * Registers and caches enums and provides validation functions.
 * 
 * Thanks to @see http://php.net/manual/en/class.splenum.php#117247
 */
class Enum
{
    private static $constsCache = null;
    private function __construct()
    {
        //prevent instance
    }

    public static function isValidName($name)
    {
        return array_key_exists($name, self::getConstants());
    } 

    public static function isValidValue($value) { 
        $values = array_values(self::getConstants());
        return in_array($value, $values, $strict = true);
    }

    static private function getConstants() : array
    {
        if (self::$constsCache == null) {
            self::$constsCache = [];
        }

        $clazz = get_called_class();
        if (!array_key_exists($clazz, self::$constsCache)) {
            $reflect = new \ReflectionClass($clazz);
            self::$constsCache[$clazz] = $reflect->getConstants();
        }

        return self::$constsCache[$clazz];
    } 
}
