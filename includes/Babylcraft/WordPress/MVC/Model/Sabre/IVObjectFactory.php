<?php

namespace Babylcraft\WordPress\MVC\Model\Sabre;

use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Component\VCalendar;
use Babylcraft\WordPress\MVC\Model\IEventModel;
use Babylcraft\WordPress\MVC\Model\ICalendarModel;
use Sabre\VObject\Property\ICalendar\Recur;

interface IVObjectFactory
{
    function getAsVCalendar(ICalendarModel $calendar) : VCalendar;
    function copyToVCalendar(IEventModel $event, VCalendar $vcalendar) : VEvent;
    function copyToVEvent(IEventModel $variation, VEvent $vevent) : Recur;
}