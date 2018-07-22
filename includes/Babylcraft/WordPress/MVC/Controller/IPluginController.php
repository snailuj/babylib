<?php

namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\Plugin\IBabylonPlugin;
use Babylcraft\WordPress\MVC\Controller\Config\IControllerConfig;

interface IPluginController
{
    public function configure(IBabylonPlugin $plugin, string $controllerName);
}
