<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\CalDAV;

use Babylcraft\WordPress;
use Sabre\CalDAV\CalendarObject;

/**
 * @copyright Copyright (c) Babylon Codecraft Ltd
 * @author Julian Suggate
 */
class Calendar extends CalDAV\Calendar
{
    /**
     * Creates a new Calendar object with the given name
     * and owner. Full permissions are granted to the owner.
     * 
     * @param $name string  Name of the Calendar
     * @param $owner string 
     */
    function __construct(string $name, string $ownerUri)
    {   //TODO put these somewhere else obvs
        $pdo = new \PDO("mysql:dbname=". DB_NAME .";host=". DB_HOST, DB_USER, DB_PASSWORD);
        $backend = new CalDAV\Backend\SimplePDO($pdo);

        $props = 
        [
            'uri' => $name,
            'principalUri' => $owner
        ];

        super::__construct($backend, $props);
    }

    /**
     * Reimplementation of Sabre\CalDAV\Calendar.getProperties()
     * to actually honour $requestedProperties and improve perf
     */
    function getProperties($requestedProperties)
    {
        $response = [];

        foreach ($requestedProperties as $index => $propName) {
            if ((is_null($this->calendarInfo[$propName]) || $propName[0] !== '{')) {
                continue;
            }
            
            $response[$propName] = $this->calendarInfo[$propName];
        }

        return $response;
    }
}
?>