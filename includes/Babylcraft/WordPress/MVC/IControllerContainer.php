<?php
namespace Babylcraft\WordPress\MVC;

use Babylcraft\WordPress\MVC\Controller\IPluginController;


interface IControllerContainer
{
    function getController(string $controllerName);
}
