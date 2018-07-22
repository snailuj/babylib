<?php
namespace Babylcraft;

use Pimple\Container;
use DownShift\Wordpress\EventEmitterInterface;

use Babylcraft\WordPress\Plugin\IBabylonPlugin;

class Babylon extends Container
{
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
