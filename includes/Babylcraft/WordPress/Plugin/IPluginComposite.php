<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\Babylon;
use Babylcraft\WordPress\MVC\IControllerContainer;
use Babylcraft\WordPress\Plugin\Config\IPluginCompositeConfig;

interface IPluginComposite extends IBabylonPlugin
{
    public function hydrateAll();

    public function getPlugin(string $pluginName) : array;
}