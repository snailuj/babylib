<?php
namespace Babylcraft\WordPress;

/**
 * Allows classes to mixin db access to their implementation
 */
trait DBAPI
{
    function dbDelta($queries = '', bool $execute = true) 
    {
        return dbDelta($queries, $execute);
    }

    function getWPTablePrefix() : string
    {
        global $wpdb;
        return $wpdb->prefix;
    }

    function getCharsetCollate() : string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }

    public static function isBabylonPluginActive(string $pluginName) : bool {
        $pluginLocation = "{$pluginName}/{$pluginName}.php";
        return in_array($pluginLocation, get_option('active_plugins', []));
    }

    public function getOption(string $optionName) {
        return get_option($optionName);
    }

    public function setOption(string $optionName, $optionValue) : bool {
        return update_option($optionName, $optionValue);
    }
}
?>