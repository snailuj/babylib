<?php

namespace Babylcraft\WordPress\MVC\Model\Sabre;

use Sabre\VObject\Node;


interface IVObjectClient
{
    function setVObjectFactory(IVObjectFactory $factory);

    /**
     * Returns this model as a VObject.
     */
    function asVObject() : Node;
}