<?php

namespace Babylcraft\GraphQL\TypeDef;

use Babylcraft\WordPress\PluginAPI;

use WPGraphQL\Type\WPObjectType;
use GraphQL\Type\Definition\Type;

abstract class BabylonType extends WPObjectType
{
    /**
     * @var callable
     */
    protected static $fieldDefs;

    public function __construct() {
        $config = [
            'name'          => static::getName(),
            'fields'        => static::fields(),
            'description'   => static::getDescription()
        ];

        parent::__construct( $config );
    }

    abstract static protected function getName() : string;
    abstract static protected function getDescription() : string;
    abstract static protected function getFieldDefs() : array;

    static protected function fields() {
        if (null === self::$fieldDefs) {
            self::$fieldDefs = function() {
                $defs = self::prepare_fields(self::getFieldDefs(), self::getName());

                return $defs;
            };
        }

        return self::$fieldDefs;
    }

    static protected function makeFieldDef(Type $type, string $description) : array
    {
        return [
            'type' =>        $type,
            'description' => $description
        ];
    }
}