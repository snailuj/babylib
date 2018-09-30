<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\CalDAV;
use Sabre\VObject;

class SabreFacade
{
    const CALENDAR_TABLENAME = 'calendars';
    const CALENDAR_INSTANCES_TABLENAME = 'calendarinstances';
    const CALENDAR_OBJECT_TABLENAME = 'calendarobjects';
    const CALENDAR_CHANGES_TABLENAME = 'calendarchanges';
    // const SCHEDULING_OBJECT_TABLENAME = 'schedulingobjects';
    // const CALENDAR_SUBSCRIPTIONS_TABLENAME = 'calendarsubscriptions';

    /**
     * @var Sabre\CalDAV\Backend\PDO An instance of PDO-aware class used by the Sabre
     * CalDAV library for storing / retrieving iCalendar objects
     */
    private $caldav;

    private $timezonePropName;

    public function __construct(\PDO $pdo, string $tableNamespace)
    {
        $this->caldav = new CalDAV\Backend\PDO($pdo);
        $this->timezonePropName = array_search("timezone", $this->caldav->propertyMap);
        $this->caldav->calendarTableName              = $tableNamespace . self::CALENDAR_TABLENAME;
        $this->caldav->calendarInstancesTableName     = $tableNamespace . self::CALENDAR_INSTANCES_TABLENAME;
        $this->caldav->calendarObjectTableName        = $tableNamespace . self::CALENDAR_OBJECT_TABLENAME;
        $this->caldav->calendarChangesTableName       = $tableNamespace . self::CALENDAR_CHANGES_TABLENAME;
        // $this->caldav->schedulingObjectTableName      = $tableNamespace . self::SCHEDULING_OBJECT_TABLENAME;
        // $this->caldav->calendarSubscriptionsTableName = $tableNamespace . self::CALENDAR_SUBSCRIPTIONS_TABLENAME;
    }

    public function createCalendar(string $owner, string $uri, string $tz = 'UTC') : array
    {
        return $this->caldav->createCalendar($owner, $uri, [ $this->timezonePropName => $tz ]);
    }

    public function getCalendarForOwner(string $owner, string $uri) : VObject\Component\VCalendar
    {   //no more performant way to do this that I can find with the weird CalDAV API
        $calendars = $this->getCalendarsForOwner($owner);
        return $calendars[$uri] ?? null;
    }

    public function getCalendarsForOwner(string $owner) : array
    {
        $calendars = [];
        foreach ( $this->caldav->getCalendarsForUser($owner) as $calendarInfo ) {
            $root = new VObject\Component\VCalendar($calendarInfo, false);
            $root->URI = $calendarInfo['uri'];
            $root->TZID = $calendarInfo[$this->timezonePropName];

            $objects = $this->caldav->getCalendarObjects($calendarInfo['id']);

            //
            // The following properties generate warnings from deep inside sabre/vobject on serialization
            // '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set'
            //  and
            // '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'
            //
            //  I have no clue why, or what those properties are for. They come out as empty from the
            //  serializing process, but I have decided to swallow the warnings via '@' on the call to jsonSerialize() 
            //  and assume that's expected behaviour (even if nasty). So I can continue with my project and not 
            //  fix bugs on CalDAV.
            //  
            //  May have to revisit this if those props are turn out crucial to some iCalendar clients.
            //
            //  Uncomment the following lines to prevent the PHP warnings
            //
                  //unset($calendarInfo['{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set']);
                  //unset($calendarInfo['{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'        ]);
            //  
            //  Rather than solving the problem, that just means the props will be removed from the output
            //  entirely instead of being set to empty string as is currently the case.
            //
            $objectURIs = array_column($objects, 'uri');
            $calendarObjects = $this->caldav->getMultipleCalendarObjects($calendarInfo['id'], $objectURIs);
            foreach ( $calendarObjects as $calendarObject ) {
                $object = VObject\Reader::read($calendarObject['calendardata']);
                //\Babylcraft\WordPress\PluginAPI::debugContent($calendarObject, "::getCalendarsForOwner() got calendar object: ");
                $object->add("DB_ID", $calendarObject['id']);
                $root->add($object);
            }

            $calendars[$calendarInfo['uri']] = $root;
        }

        return $calendars;
    }

    /**
     * Adds an event with the given properties to the calendar given by the weird CalDAV array-as-id thingy.
     * 
     * Non-standard parameters are supported as sibling elements in both the $event and sub-arrays within $variations.
     * 
     * Format for $event is like:
     * <pre>
     * $event = [
     *     "RRULE"    => <rrule definition>,
     *     "X-"<non std prop1> => <property value x>,
     *     "X-"<non std prop2> => <property value y>
     * ]
     * 
     * </pre>
     * 
     * Note that non-standard parameters are siblings of RRULE (in iCalendar terms, direct children of a VEVENT [which
     * is a "component"], the RRULE is a "property", and here the X-* items are "unknown properties"). 
     * 
     * Format for $variations is like:
     * 
     * <pre>
     * $variations[0] = [
     *     "EXRULE"   => <exrule definition 1>,
     *     "children" => [
     *         "X-UID"             => <unique identifier1>,
     *         "X-"<non std prop1> => <propert value a>
     *     ]
     * ]
     * $variations[1] = [
     *     "EXRULE"   => <exrule definition 2>,
     *     "children" => [
     *         "X-UID"             => <unique identifier2>,
     *         "X-"<non std prop1> => <property value b>,
     *         "X-"<non std prop2> => <property value c>
     *     ]
     * ]
     *   ...
     * ]
     * </pre>
     * 
     * Here, the "X-" are children of an EXRULE, which in iCalendar terms is a "property" (unlike VEVENT which is
     * a "component"). Children of _properties_ are referred to as "parameters". Therefore, the X-* items inside
     * $variations are (again, in iCalendar-speak) called "Non-Standard Parameters". The distinction between 
     * "parameters" (here in $variations) and "properties" (in $event) is almost meaningless unless you are parsing
     * iCalendar-formatted strings, so it helps to just think of them all as "children" of either a VEVENT or an EXRULE.
     * 
     * If any children are given in a given variation, then "X-UID" is required for that variation (throws 
     * FieldException::ERR_IS_NULL if missing), because all variations incl their params are denormalised into 
     * strings and stored in a single column on the table that stores events. This is a CalDAV thing. Without an 
     * identifier, there would be no guaranteed way of finding your variation next time. So we check for that.
     * You're welcome.
     * 
     * The parameters in $variations are not validated against those in $event.
     * 
     * Except for X-UID, the following apply to children of variations:
     * * The CalDAV spec states that Non-Standard parameters should begin with "X-", but this is not 
     * enforced (nor is it enforced by any other iCalendar or jCal library I have used).
     * * Non-standard parameters are entirely optional 
     * * Both the key and the value are free-form strings (will be sanitised for DB insertion, however).
     * 
     * @example:
     * 
     * <pre>
     * $event = [
     *     "RRULE"          => "FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR","FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR"
     *     "X-COFFEE_ORDER" => "No fraps!",
     *     "X-TOPIC"        => "Dependent origination"
     * ]
     * $variations = [
     *     0 => [
     *         "EXRULE"         => "FREQ=YEARLY;INTERVAL=1;BYMONTH=9;BYDAY=1MO,1TU,1WE,1TH,1FR",
     *         "children"       => [
     *             "X-UID"          => "{6CCEC704-826D-B7F0-330B-F096D7B7339E}",
     *             "X-COFFEE_ORDER" => "I can haz frappacino",
     *             "X-TOPIC"        => "Boss away, kittehs play"
     *         ]
     *     ],
     *     1 => [
     *         ... //second variation definition
     *     ],
     *     ... //other variations
     * ]
     * </pre>
     * 
     * Doesn't support EXDATE. Just use a variation with an EXRULE that limits to a singular date.
     * 
     * @param array     $calendarId     ID of the parent calendar
     * @param string    $uri            URI of the event -- must be unique across this calendar
     * @param string    $event[SUMMARY] A description of the event
     * @param [string]  $event[DTSTART] The start date and time of the event
     * @param [string]  $event[RRULE]   Recurrence rule as in iCalendar Spec. <a href="https://icalendar.org/rrule-tool.html">Generate one here</a>.
     * @param array     $variations     List of associative arrays to be added as exceptions to the event schedule.
     * @param string    $variations[X-UID] Unique identifier for the variation.
     * 
     * @return Sabre\VObject\Component\VCalendar    iCalendar dictates that every object must be wrapped in a VCalendar. saveEvent()->VEVENT will
     *                                              hold the CalDAV representation of the actual event that was added.
     */
    public function createEvent( array $calendarId, string $uri, array $event, array $variations = [] ) : VObject\Component\VCalendar
    {
        //\Babylcraft\WordPress\PluginAPI::debugContent([$event, $variations], "::createEvent() [event, variations] = ");
        $root = new VObject\Component\VCalendar([ "VEVENT" => $event ], $defaults = false);

        //add EXRULEs
        foreach ($variations as $variation) {
            $exrule = $variation["EXRULE"] ?? null;
            if ($exrule === null) {
                throw new FieldException(FieldException::ERR_IS_NULL, "EXRULE");
            }

            $children = $variation["children"] ?? [];
            $root->VEVENT->add("EXRULE", $exrule, $children);
        }

        $this->caldav->createCalendarObject($calendarId, $uri, $root->serialize());

        return $root;
    }

    #region Schema Definition
    /**
     * WILL DELETE ALL CALENDAR DATA IF EXISTS
     */
    static public function getSchema(
        string $tableNamespace,
        string $wpTableNamespace,
        string $charsetCollate,
        bool $drop = false
    ) : string
    {
        $calendarObjectTableName =          $tableNamespace . static::CALENDAR_OBJECT_TABLENAME;
        $calendarTableName =                $tableNamespace . static::CALENDAR_TABLENAME;
        $calendarInstancesTableName =       $tableNamespace . static::CALENDAR_INSTANCES_TABLENAME;
        $calendarChangesTableName =         $tableNamespace . static::CALENDAR_CHANGES_TABLENAME;
        // $calendarSubscriptionsTableName =   $tableNamespace . static::CALENDAR_SUBSCRIPTIONS_TABLENAME;
        // $schedulingObjectTableName =        $tableNamespace . static::SCHEDULING_OBJECT_TABLENAME;

        if ($drop) {
            $sql = 
            "DROP TABLE IF EXISTS {$calendarObjectTableName};
             DROP TABLE IF EXISTS {$calendarTableName};
             DROP TABLE IF EXISTS {$calendarInstancesTableName};
             DROP TABLE IF EXISTS {$calendarChangesTableName};
            ";
            //  DROP TABLE IF EXISTS {$calendarSubscriptionsTableName};
            //  DROP TABLE IF EXISTS {$schedulingObjectTableName};
        } else {
            $sql =
            //CalDAV tables
            "CREATE TABLE {$calendarObjectTableName} (
                id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                calendardata MEDIUMBLOB,
                uri VARBINARY(200),
                calendarid INTEGER UNSIGNED NOT NULL,
                lastmodified INT(11) UNSIGNED,
                etag VARBINARY(32),
                size INT(11) UNSIGNED NOT NULL,
                componenttype VARBINARY(8),
                firstoccurence INT(11) UNSIGNED,
                lastoccurence INT(11) UNSIGNED,
                uid VARBINARY(200),
                UNIQUE(calendarid, uri),
                INDEX calendarid_time (calendarid, firstoccurence)
            ) ENGINE=InnoDB $charsetCollate;
            
            CREATE TABLE {$calendarTableName} (
                id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                synctoken INTEGER UNSIGNED NOT NULL DEFAULT '1',
                components VARBINARY(21)
            ) ENGINE=InnoDB $charsetCollate;
            
            CREATE TABLE {$calendarInstancesTableName} (
                id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                calendarid INTEGER UNSIGNED NOT NULL,
                principaluri VARBINARY(100),
                access TINYINT(1) NOT NULL DEFAULT '1' COMMENT '1 = owner, 2 = read, 3 = readwrite',
                displayname VARCHAR(100),
                uri VARBINARY(200),
                description TEXT,
                calendarorder INT(11) UNSIGNED NOT NULL DEFAULT '0',
                calendarcolor VARBINARY(10),
                timezone TEXT,
                transparent TINYINT(1) NOT NULL DEFAULT '0',
                share_href VARBINARY(100),
                share_displayname VARCHAR(100),
                share_invitestatus TINYINT(1) NOT NULL DEFAULT '2' COMMENT '1 = noresponse, 2 = accepted, 3 = declined, 4 = invalid',
                UNIQUE(principaluri, uri),
                UNIQUE(calendarid, principaluri),
                UNIQUE(calendarid, share_href)
            ) ENGINE=InnoDB $charsetCollate;
            
            CREATE TABLE {$calendarChangesTableName} (
                id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                uri VARBINARY(200) NOT NULL,
                synctoken INT(11) UNSIGNED NOT NULL,
                calendarid INT(11) UNSIGNED NOT NULL,
                operation TINYINT(1) NOT NULL,
                INDEX calendarid_synctoken (calendarid, synctoken)
            ) ENGINE=InnoDB $charsetCollate;
            
            ";
            // CREATE TABLE {$calendarSubscriptionsTableName} (
            //     id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            //     uri VARBINARY(200) NOT NULL,
            //     principaluri VARBINARY(100) NOT NULL,
            //     source TEXT,
            //     displayname VARCHAR(100),
            //     refreshrate VARCHAR(10),
            //     calendarorder INT(11) UNSIGNED NOT NULL DEFAULT '0',
            //     calendarcolor VARBINARY(10),
            //     striptodos TINYINT(1) NULL,
            //     stripalarms TINYINT(1) NULL,
            //     stripattachments TINYINT(1) NULL,
            //     lastmodified INT(11) UNSIGNED,
            //     UNIQUE(principaluri, uri)
            // ) ENGINE=InnoDB $charsetCollate;
            
            // CREATE TABLE {$schedulingObjectTableName} (
            //     id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            //     principaluri VARBINARY(255),
            //     calendardata MEDIUMBLOB,
            //     uri VARBINARY(200),
            //     lastmodified INT(11) UNSIGNED,
            //     etag VARBINARY(32),
            //     size INT(11) UNSIGNED NOT NULL
            // ) ENGINE=InnoDB $charsetCollate;
        }
        
        //\Babylcraft\WordPress\PluginAPI::debugContent($sql, "SabreFacade getSchema(): ");

        return $sql;
    }
    #endregion
}