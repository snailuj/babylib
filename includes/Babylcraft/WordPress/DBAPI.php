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

    function newPDOPlease() : \PDO
    {
        return new \PDO("mysql:dbname=". DB_NAME .";host=". DB_HOST, DB_USER, DB_PASSWORD);
    }

    function getTablePrefix() : string
    {
        global $wpdb;
        return $wpdb->prefix;
    }

    function getCharsetCollate() : string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }
}
?>