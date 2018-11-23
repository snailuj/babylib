<?php

namespace Babylcraft\WordPress\MVC\Model;

use Sabre\VObject\Component\VEvent;


interface IModelFactory
{
    function cloneDBConnections(IModelFactory $to) : void;

    function createCalendarSchema() : void;
    function deleteCalendarSchema() : void;

    /**
     * Returns the FQN of the class that is used by this factory when creating objects that implement
     * the given interface.
     * 
     * @param string $interface The interface whose implementation you wish to retrieve
     * 
     * @throws ModelException ERR_UNKNOWN_MAPPING if the given interface is not able to be resolved
     * to an implementation
     */
    function getImplementingClass(string $interface) : string;

    /**
     * Returns the FQN of the interface that this factory looks for as a command to create objects 
     * with type equal to that of the given model.
     * 
     * @param IBabylonModel $model The model object whose interface you want to get
     * 
     * @throws ModelException ERR_UNKNOWN_MAPPING if the given $model has not corresponding interface
     * in this model factory
     */
    function getModelInterface(IBabylonModel $model) : string;

    function persist(IBabylonModel $model) : void;

    /**
     * Loads data into the given model by its ID, throws exception if no ID is present.
     */
    function hydrate(IBabylonModel $model) : void;

    /**
     * Query the data-store for all data that are children of the given model having type $childInterfaceName. 
     * Child *Model objects that implement $childInterfaceName will then be created for each record retrieved,
     * and hydrated with the queried data.
     * 
     * Leave $childInterfaceName blank to load children of all types that belong to $model.
     */
    function newHydratedChildren(IBabylonModel $model, ?string $childInterfaceName = null) : void;

    /**
     * Creates a new ICalendarModel. Multiple calendars can be specified with differing $uri for the 
     * same $owner, and multiple $owners can have calendars with the same $uri. But $uri and $owner must together 
     * be unique (violations will only be picked up when you attempt to create or update the second calendar).
     * 
     * @param string $owner  An identifier for the owner of this calendar
     * @param string $uri    An identifier for this calendar within the owner's urispace
     * @param [string] $tz   The timezone, in <a href="https://en.wikipedia.org/wiki/Tz_database">Olson ID</a> 
     * format -- defaults to (and recommended as) UTC.
     * 
     * @return ICalendarModel The new ICalendarModel instance
     */
    function newCalendar(string $owner, string $uri, string $tz = 'UTC') : ICalendarModel;

    /**
     * Creates a new IEventModel with the given name and recurrence rule, and adds it as an event on the 
     * ICalendarModel given in the first arg.
     * 
     * @param ICalendarModel $parent Calendar to which you wish to add a event
     * @param string $name Name of the event, can be any string
     * @param string $rrule Recurrence rule for the event
     * @param \DateTime $start Date and time of the event start
     * 
     * @return IEventModel The IEventModel object that represents the event
     */
    function newEvent(ICalendarModel $parent, string $name, string $rrule, \DateTimeInterface $start, string $uid) : IEventModel;

    /**
     * Creates a new IEventModel with the given name and recurrence rule, and adds it as a variation
     * to the IEventModel given in first arg.
     * 
     * @param IEventModel $parent Event to which you wish to add a variation
     * @param string $name Name of the variation, can be any string
     * @param string $rrule Recurrence rule for the variation
     * 
     * @return IEventModel The IEventModel object that represents the variation
     */
    function newVariation(IEventModel $parent, string $name, string $rrule, string $uid) : IEventModel;
}
