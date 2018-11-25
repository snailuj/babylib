<?php

namespace Babylcraft\WordPress\MVC\Model\Sabre;

use Sabre\CalDAV;
use Sabre\VObject;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ModelException;
use Sabre\VObject\Property\ICalendar\Recur;
use Babylcraft\Util;

class SabreFacade implements IVObjectFactory
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

    #region Public
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

    public function loadVCalendar(ICalendarModel $babylCalendar) : VCalendar
    {
        if ( !$vcalendar = $this->loadCalendarForOwner(
            $babylCalendar->getValue(ICalendarModel::F_OWNER),
            $babylCalendar->getValue(ICalendarModel::F_URI))
        ) {
            throw new ModelException(ModelException::ERR_RECORD_NOT_FOUND, 
                " owner = ". $babylCalendar->getValue(ICalendarModel::F_OWNER)
                .", uri = ". $babylCalendar->getValue(ICalendarModel::F_URI));
        }

        return $vcalendar;
    }

    public function loadVEvent(IEventModel $babylEvent) : VEvent
    {
        if ( !$vevent = $this->loadEventForCalendar(
            $babylEvent->getParent()->getValue(ICalendarModel::F_OWNER),
            $babylEvent->getParent()->getValue(ICalendarModel::F_URI),
            $babylEvent->getValue(IEventModel::F_NAME))
        ) {
            throw new ModelException(ModelException::ERR_RECORD_NOT_FOUND,
                " owner = ". $babylEvent->getParent()->getValue(ICalendarModel::F_OWNER)
                .", uri = ". $babylEvent->getParent()->getValue(ICalendarModel::F_URI)
                .", eventUri = ". $babylEvent->getValue(IEventModel::F_NAME) );
        }

        return $vevent;
    }

    public function createCalendar(ICalendarModel $babylCalendar) : array
    {
        try {
            return $this->caldav->createCalendar(
                $babylCalendar->getValue(ICalendarModel::F_OWNER),
                $babylCalendar->getValue(ICalendarModel::F_URI),
                [ $this->timezonePropName => $babylCalendar->getValue(IcalendarModel::F_TZ) ]
            );
        } catch ( \PDOException $e ) {
            throw Util::newModelPDOException($e);
        }
    }

    /**
     * Adds a VEVENT by serializing the given `IEventModel` and any variations (which will be stored as EXRULEs).
     * 
     * Additional non-standard values can be tacked onto the serialized `IEventModel` (both events and
     * variations), enabling the use of iCalendar to store arbitrary data associated with date recurrence, hence
     * this whole `SabreFacade`, which enables us to use the iCalendar recurrence rules and the libraries out there
     * to specify recurrence of anything we like (bookings, for example). Despite it's utility, this has been a big
     * fuck-around in coding effort, and could perhaps be renamed `SabreCharade`.
     * 
     * Some terminology is necessary. In iCalendar terms, a VEVENT is a "Component". Direct children of a Component
     * are known as Properties. RRULE is a known Property of VEVENT, and any other items you choose to serialize 
     * out from your `IEventModel` are called "Unknown Properties". Unknown Properties are permitted on VEVENTs by
     * the iCalendar spec.
     * 
     * A serialized event variation, however is saved as an EXRULE, which is itself a Property of a VEVENT (just 
     * like an RRULE). Children of *Properties* are called "Parameters".
     * 
     * Therefore, the non-standard items inside variations are (again, in iCalendar-speak) called "Non-Standard 
     * Parameters". Having blinded you with this science, let it now be said that the distinction between Parameters
     * (in variations) and Properties (in events) is almost meaningless unless you are parsing iCalendar-formatted 
     * strings.
     * 
     * However, it is helpful to understand the terminology above when explaining the format to use when
     * serializing your `IEventModel` objects (or perhaps more importantly, any subclasses you choose to create).
     * 
     * A serialized event should have the following format:
     * <pre>
     * $event = [
     *     "RRULE"   => <rrule definition>,
     *     "UID"     => <uid>,
     *     "URI"     => <uri>,
     *     "DTSTART" => <start date and time>,
     *     "X-<non std prop1>" => <property value x>,
     *     "X-<non std prop2>" => <property value y>
     * ]
     * </pre>
     *       
     * 
     * <pre>
     * $variation = [
     *     0 => <exrule definition 1>,
     *     1 => [
     *         "X-UID"             => <unique identifier1>,
     *         "X-<non std param1> => <param value a>
     *     ]
     * ]
     * </pre>
     * 
     * Note that variations must serialize into a numerically-indexed array, as opposed to the associative array that 
     * an event must serialize to.
     * 
     * If any non-standard Parameters are present in a variation, then "X-UID" is required for that variation (throws 
     * FieldException::ERR_IS_NULL if missing). All variations incl their params are denormalised into strings and 
     * stored in a single column on the table that stores events. This is a Sabre CalDAV thing, so don't bitch to me
     * about it. Without an identifier, there would be no guaranteed way of finding your variation next time. So we 
     * check for that. You're welcome.
     * 
     * The following apply to non-standard Parameters of variations (except for X-UID already mentioned):
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
     *         0  => "FREQ=YEARLY;INTERVAL=1;BYMONTH=9;BYDAY=1MO,1TU,1WE,1TH,1FR",
     *         1  => [
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
     * @param IEventModel $event The event to serialize and persist via Sabre.
     * 
     * @return Sabre\VObject\Component\VCalendar    iCalendar dictates that every object must be wrapped in a VCalendar. saveEvent()->VEVENT will
     *                                              hold the CalDAV representation of the actual event that was added.
     */
    public function createEvent(IEventModel $event) : int
    {
        $vcalendar = $this->eventToVEvent($event)->getRoot();

        try {
            return $this->caldav->createObjectSimple(
                $event->getParent()->getId(),
                $event->getValue(IEventModel::F_NAME),
                $vcalendar->serialize()
            );
        } catch ( \PDOException $e ) {
            throw Util::newModelPDOException($e);
        }        
    }

    public function updateCalendar(ICalendarModel $model) : void
    {
        try {
            $this->caldav->updateCalendarSimple(
                $model->getValue(ICalendarModel::F_OWNER),
                $model->getValue(ICalendarModel::F_URI),
                [
                    'timezone' => $model->getValue(ICalendarModel::F_TZ)
                ]
            );
        } catch ( \PDOException $e ) {
            throw Util::newModelPDOException($e);
        }
    }

    public function updateEvent(IEventModel $event) : void
    {
        $vcalendar = $this->eventToVEvent($event)->getRoot();

        try {
            $this->caldav->updateCalendarObject(
                $event->getParent()->getId(),
                $event->getValue(IEventModel::F_NAME),
                $vcalendar->serialize()
            );
        } catch ( \PDOException $e ) {
            throw Util::newModelPDOException($e);
        }
    }
    #endregion
    
    #region IVObjectFactory implementation
    public function calendarToVCalendar(ICalendarModel $calendar): VCalendar
    {
        $root = new VCalendar([], $defaults = false);

        $root->TZID         = $calendar->getValue(ICalendarMOdel::F_TZ);
        $root->URI          = $calendar->getValue(ICalendarModel::F_URI);
        $root->PRINCIPALURI = $calendar->getValue(ICalendarModel::F_OWNER);

        foreach ( $calendar->getEvents() as $event ) {
            if ( $event instanceof IEventModel ) {
                $this->eventToVEvent($event, $root);
            } else {
                throw new ModelException(ModelException::ERR_WRONG_TYPE, "IEventModel expected from ICalendarModel::getEvents(), got ". get_class($event));
            }
        }

        return $root;
    }

    #region IVObjectFactory implementation
    public function getAsVCalendar(ICalendarModel $calendar) : VCalendar
    {
        return $this->calendarToVCalendar($calendar);
    }

    public function copyToVCalendar(IEventModel $event, VCalendar $vcalendar) : VEvent
    {
        return $this->eventToVEvent($event, $vcalendar);
    }

    public function copyToVEvent(IEventModel $variation, VEvent $vevent) : Recur
    {
        return $this->variationToExrule($variation, $vevent);
    }
    #endregion
    /**
     * Converts the given IEventModel into a VEvent and returns it.
     * The VCalendar used to create the VEvent can be accessed via `eventToVevent()->root`
     */
    public function eventToVEvent(IEventModel $event, VCalendar $root = null) : VEvent
    {
        $root = $root ?? new VCalendar([], $defaults = false);

        if ($vevent = $root->searchByURI($event->getValue(IEventModel::F_NAME)) ) {
            //found a matching VEVENT, remove it to avoid duplicates (URI is unique in Babylcraft ICal implementation)
            $root->remove($vevent);
        }

        $vevent = $root->add("VEVENT", $event->getSerializable());

        //add EXRULEs
        foreach ($event->getVariations() as $variation) {
            $recur = $this->variationToExrule($variation, $vevent);
        }

        return $vevent;
    }

    public function variationToExrule(IEventModel $variation, VEvent $parent = null) : Recur
    {
        $parent = $parent ?? (new VCalendar($defaults = false))->add("VEVENT");
        $data = $variation->getSerializable();
        return $parent->add("EXRULE", $data[0], $data[1]);
    }
    #endregion

    #region CalDAV interactions (Private)
    private function loadCalendarForOwner(string $owner, string $uri) : ?VCalendar
    {   //no more performant way to do this that I can find with the weird CalDAV API
        $calendars = $this->loadCalendarsForOwner($owner);
        return $calendars[$uri] ?? null;
    }

    //TODO replace the CalDAV queries with our own that retrieve all the data in one shot
    //TODO replace the CalDAV schema with one that makes more sense and doesn't have all the cruft we don't need
    //     still needs to be parseable into VObjects though (which have all the gnarly recurrence rules that we want)
    //     that should be relatively easy though, because they're all just created from dicts of DB data 
    private function loadCalendarsForOwner(string $owner) : array
    {
        $calendars = [];
        try {
            $rows = $this->caldav->getCalendarsForUser($owner);
        } catch ( \PDOException $e ) {
            throw Util::newModelPDOException($e);
        }

        foreach ( $rows as $row ) {
            $root = new VObject\Component\VCalendar($row, false);
            $root->URI = $row['uri'];
            $root->TZID = $row[$this->timezonePropName];

            try {                
                $objects = $this->caldav->getCalendarObjects($row['id']);
            } catch ( \PDOException $e ) {
                throw Util::newModelPDOException($e);
            }

            // More Sabre weirdness
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
                   //unset($row['{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set']);
                   //unset($row['{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'        ]);
             //  
             //  Rather than solving the problem, that just means the props will be removed from the output
             //  entirely instead of being set to empty string as is currently the case.
            //
            $objectURIs = array_column($objects, 'uri');
            try {                
                $calendarObjects = $this->caldav->getMultipleCalendarObjects($row['id'], $objectURIs);
            } catch ( \PDOException $e ) {
                throw Util::newModelPDOException($e);
            }

            foreach ( $calendarObjects as $calendarObjectData ) {                
                $root->add($this->dbToVObject($calendarObjectData));
            }

            $calendars[$row['uri']] = $root;
        }

        return $calendars;
    }

    private function loadEventForCalendar(string $calendarOwner, string $calendarUri, string $eventUri) : array
    {
        try {
            $objectData = $this->caldav->getObjectFromCalendarPath($calendarOwner, $calendarUri, $eventUri);
        } catch ( \PDOException $e ) {
            throw Util::newModelPDOException($e);
        }

        return $this->dbToVObject($objectData);
    }

    private function dbToVObject(array $calendarObjectData) : VObject\Component
    {
        $component = VObject\Reader::read($calendarObjectData['calendardata']);
        $component = current($component->getComponents()); //strip off the wrapper VCalendar (ICal dumbness)
        $component->add("DB_ID", intval($calendarObjectData['id']));

        return $component;
    }
    #endregion

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