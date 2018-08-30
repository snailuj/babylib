<?php

namespace Babylcraft\WordPress\MVC\Model;

interface IModelFactory
{
    function createCalendarSchema() : void;
    function deleteCalendarSchema() : void;
    function cloneDBConnections(IModelFactory $to) : void;
}