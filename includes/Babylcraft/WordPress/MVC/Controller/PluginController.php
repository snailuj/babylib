<?php
namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\Plugin\IBabylonPlugin;

use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

/**
 * Template class for Controller objects
 */
abstract class PluginController implements IPluginController
{
    private $viewLocationURI;

    protected $controllerName;

    /**
     * Create a new Controller, passing in dependencies
     * @param PluginAPI   object for hooking into WordPress events
     */
    public function configure(IBabylonPlugin $plugin, string $controllerName)
    {
        $this->controllerName = $controllerName;
        $this->plugin = $plugin;
        $this->selfRegisterHooks();
    }

    protected function selfRegisterHooks()
    {
        $this->registerHooks();
    }

    abstract protected function registerHooks();
}
