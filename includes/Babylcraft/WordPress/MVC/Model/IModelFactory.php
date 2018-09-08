<?php

namespace Babylcraft\WordPress\MVC\Model;

interface IModelFactory
{
    function cloneDBConnections(IModelFactory $to) : void;

    function createCalendarSchema() : void;
    function deleteCalendarSchema() : void;

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
    function calendar(string $owner, string $uri, string $tz = 'UTC') : ICalendarModel;

    /**
     * Creates a new IEventModel with the given name and recurrence rule, and adds it as an event on the 
     * ICalendarModel given in the first arg.
     * 
     * @param ICalendarModel $calendar Calendar to which you wish to add a event
     * @param string $name Name of the event, can be any string
     * @param string $rrule Recurrence rule for the event
     * @param \DateTime $start Date and time of the event start
     * @param [array] $fields Optional fields to be set during instantiation
     * 
     * @return IEventModel The IEventModel object that represents the event
     */
    function event(ICalendarModel $calendar, string $name, string $rrule, \DateTime $start, array $fields = []) : IEventModel;

    /**
     * Creates a new IEventModel with the given name and recurrence rule, and adds it as a variation
     * to the IEventModel given in first arg.
     * 
     * @param IEventModel $event Event to which you wish to add a variation
     * @param string $name Name of the variation, can be any string
     * @param string $rrule Recurrence rule for the variation
     * @param [array] $fields Optional fields to be set during instantiation
     * 
     * @return IEventModel The IEventModel object that represents the variation
     */
    function eventVariation(IEventModel $event, string $name, string $rrule, array $fields = []) : IEventModel;
}
