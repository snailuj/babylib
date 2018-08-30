<?php

namespace Babylcraft\WordPress\MVC\Model\Impl;

use Babylcraft\WordPress\MVC\Model\ICalendarFactory;
use Babylcraft\WordPress\MVC\Model\Impl\BabylonModel;

use Sabre\VObject\Component\VCalendar;

class CalendarFactory extends BabylonModel implements ICalendarFactory
{
    /**
     * @var Sabre\CalDAV\CalendarBackend Does the actual saving and retrieving
     */
    private $calendarBackend;

    const CALENDAR_TABLENAME = 'calendars';
    const CALENDAR_INSTANCES_TABLENAME = 'calendarinstances';
    const CALENDAR_OBJECT_TABLENAME = 'calendarobjects';
    const CALENDAR_CHANGES_TABLENAME = 'calendarchanges';
    const SCHEDULING_OBJECT_TABLENAME = 'schedulingobjects';
    const CALENDAR_SUBSCRIPTIONS_TABLENAME = 'calendarsubscriptions';

    public function configureDB(
        \PDO $pdo,
        \wpdb $wpdb,
        string $tableNamespace = 'babyl_',
        string $wpTableNamespace = 'wp_'
    ) {
        parent::configureDB($pdo, $wpdb, $tableNamespace, $wpTableNamespace);

        $this->calendarBackend = new Sabre\CalDAV\Backend\PDO($pdo);
        $this->calendarBackend->calendarSubscriptionsTableName = $tableNamespace . this::CALENDAR_SUBSCRIPTIONS_TABLENAME;
        $this->calendarBackend->calendarTableName              = $tableNamespace . this::CALENDAR_TABLENAME;
        $this->calendarBackend->calendarInstancesTableName     = $tableNamespace . this::CALENDAR_INSTANCES_TABLENAME;
        $this->calendarBackend->calendarObjectTableName        = $tableNamespace . this::CALENDAR_OBJECT_TABLENAME;
        $this->calendarBackend->calendarChangesTableName       = $tableNamespace . this::CALENDAR_CHANGES_TABLENAME;
        $this->calendarBackend->schedulingObjectTableName      = $tableNamespace . this::SCHEDULING_OBJECT_TABLENAME;
    }

    public function createCalendar(string $owner, string $name, string $tz) : array
    {
        $timezonePropName = array_search("timezone", $this->calendarBackend->propertyMap);
        $calendarId = $this->calendarBackend->createCalendar($owner, $name, [
            $timezonePropName => $tz
        ]);

        return $calendarId;
    }

    public function addEvent(
        array $calendarID,
        string $name, 
        \DateTime $dateTimeStart, 
        string $recurrenceRule = '',
        array $exceptionRules = [],
        array $props = []
    ) : VCalendar
    {
        \Babylcraft\WordPress\PluginAPI::debug("::addEvent() adding $name");
        $root = new VObject\Component\VCalendar([
                "VEVENT" => [
                    "SUMMARY" => $name,
                    "DTSTART" => $dateTimeStart,
                ]
            ],
            $defaults = false);

        //add RRULE
        if ($recurrenceRule) {
            $root->VEVENT->add("RRULE", $recurrenceRule);
            foreach ($props as $propName => $propValue) {
                $root->VEVENT->RRULE->add($propName, $propValue);
            }
        }

        //add EXRULEs
        foreach ($exceptionRules as $uid => $definition) {
            if ($exrule = $definition["EXRULE"]) {
                unset($definition["EXRULE"]); //remove it from definition so we can use the rest as children
                $definition["X-UID"] = $uid;
                $root->VEVENT->add("EXRULE", $exrule, $children = $definition);
            }
        }

        $this->calendarBackend->createCalendarObject($calendarID, $name, $root->serialize());

        return $root;
    }

    public function getCalendarsForOwner(string $owner) : array
    {
        $calendars = [];
        foreach ( $this->calendarBackend->getCalendarsForUser($owner) as $calendarInfo ) {
            $root = new VObject\Component\VCalendar($calendarInfo, false);
            $root->URI = $calendarInfo['uri'];

            $objects = $this->calendarBackend->getCalendarObjects($calendarInfo['id']);

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
            $calendarObjects = $this->calendarBackend->getMultipleCalendarObjects($calendarInfo['id'], $objectURIs);
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

    public function getCalendarForOwner(string $owner, string $uri) : VCalendar
    {   //no more performant way to do this that I can find with the weird CalDAV API
        $calendars = $this->getCalendarsForOwner($owner);
        return isset($calendars[$uri]) ? $calendars[$uri] : null;
    }

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
        $calendarSubscriptionsTableName =   $tableNamespace . static::CALENDAR_SUBSCRIPTIONS_TABLENAME;
        $schedulingObjectTableName =        $tableNamespace . static::SCHEDULING_OBJECT_TABLENAME;

        if ($drop) {
            $sql = 
            "DROP TABLE IF EXISTS {$calendarObjectTableName};
             DROP TABLE IF EXISTS {$calendarTableName};
             DROP TABLE IF EXISTS {$calendarInstancesTableName};
             DROP TABLE IF EXISTS {$calendarChangesTableName};
             DROP TABLE IF EXISTS {$calendarSubscriptionsTableName};
             DROP TABLE IF EXISTS {$schedulingObjectTableName};
             ";
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
            
            CREATE TABLE {$calendarSubscriptionsTableName} (
                id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                uri VARBINARY(200) NOT NULL,
                principaluri VARBINARY(100) NOT NULL,
                source TEXT,
                displayname VARCHAR(100),
                refreshrate VARCHAR(10),
                calendarorder INT(11) UNSIGNED NOT NULL DEFAULT '0',
                calendarcolor VARBINARY(10),
                striptodos TINYINT(1) NULL,
                stripalarms TINYINT(1) NULL,
                stripattachments TINYINT(1) NULL,
                lastmodified INT(11) UNSIGNED,
                UNIQUE(principaluri, uri)
            ) ENGINE=InnoDB $charsetCollate;
            
            CREATE TABLE {$schedulingObjectTableName} (
                id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                principaluri VARBINARY(255),
                calendardata MEDIUMBLOB,
                uri VARBINARY(200),
                lastmodified INT(11) UNSIGNED,
                etag VARBINARY(32),
                size INT(11) UNSIGNED NOT NULL
            ) ENGINE=InnoDB $charsetCollate;
            
            ";
        }
        
        \Babylcraft\WordPress\PluginAPI::debugContent($sql, "CalendarFactory getSchema(): ");

        return $sql;
    }
}