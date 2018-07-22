<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\Babylon;
use Babylcraft\WordPress\MVC\IControllerContainer;
use Babylcraft\WordPress\Plugin\Config\IPluginCompositeConfig;

interface IPluginComposite extends IBabylonPlugin
{
    /*
    * Put things into the bag of holding
    */
    public function hydrateAll(Babylon $container, IPluginCompositeConfig $config);

    public function getPlugins() : array;
}
