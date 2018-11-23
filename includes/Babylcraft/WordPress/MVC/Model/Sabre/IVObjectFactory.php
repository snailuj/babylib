<?php

namespace Babylcraft\WordPress\MVC\Model\Sabre;

use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Component\VCalendar;
use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Sabre\VObject\Property\ICalendar\Recur;

interface IVObjectFactory
{
    public function eventToVEvent(IEventModel $event, VCalendar $root = null) : VEvent;
    public function variationToExrule(IEventModel $variation, VEvent $parent = null) : Recur;
    public function calendarToVCalendar(ICalendarModel $calendar) : VCalendar;
}