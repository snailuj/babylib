<?php

namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\Plugin\IBabylonPlugin;
use Babylcraft\WordPress\MVC\Controller\Config\IControllerConfig;
use Babylcraft\WordPress\MVC\Model\IModelFactory;

interface IPluginController
{
    /**
     * Faux-constructor, called directly after instantiation-by-reflection from containing BabylonPlugin
     * object.
     */
    function configure(IBabylonPlugin $plugin, string $controllerName);

    /**
     * Called whenever the containing BabylonPlugin is activated
     */
    function pluginActivated() : void;

    /**
     * Called whenever the containing BabylonPlugin is deactivated
     */
    function pluginDeactivated() : void;
}
