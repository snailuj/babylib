<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\MVC\IControllerContainer;
use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;
use Babylcraft\WordPress\MVC\Model\IModelFactory;

interface IBabylonPlugin
{
    public function hydrate(IPluginSingleConfig $pluginConfig, IModelFactory $modelFactory, int $logLevel);

    /*
    * Activate the plugin
    */
    public function activate();

    /*
    * Deactivate the plugin
    */
    public function deactivate();

    public function isActive() : bool;
    public function getPluginName() : string;
    public function getPluginVersion() : string;
    public function getLibPath() : string;
    public function getViewPath() : string;
}