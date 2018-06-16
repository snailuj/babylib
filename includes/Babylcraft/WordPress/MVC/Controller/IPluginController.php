<?php

namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

interface IPluginController
{
    public function configure(PluginAPI $pluginAPI, IPluginSingleConfig $pluginConfig);
}
