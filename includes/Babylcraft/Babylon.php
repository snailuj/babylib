<?php
namespace Babylcraft;

use Pimple\Container;
use DownShift\Wordpress\EventEmitterInterface;

use Babylcraft\WordPress\Plugin\IBabylonPlugin;

class Babylon extends Container
{
    const LOG_DEBUG = 0x1;
    const LOG_INFO  = 0x2;
    const LOG_WARN  = 0x4;
    const LOG_ERROR = 0x8;

    const KEY_LOG_LEVEL = "logLevel";
    const KEY_WP_EVENTS = "eventEmitter";
    const KEY_BABYLON_TOPLEVEL_PLUGIN = "babylonTopLevel";
    const KEY_PLUGIN = "Plugin";

    public function getWPEventEmitter() : EventEmitterInterface
    {
        return $this[$this::KEY_WP_EVENTS];
    }

    public function addPlugin(IBabylonPlugin $plugin)
    {
        $this[$this->getPluginKey($plugin->getPluginName())] = $plugin;
    }

    public function getPlugin(string $pluginName)
    {
        return $this[$this->getPluginKey($pluginName)];
    }

    private function getPluginKey(string $pluginName) : string
    {
        return $this::KEY_PLUGIN ."_{$pluginName}";
    }
}
