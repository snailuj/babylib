<?php
namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\Plugin\IBabylonPlugin;

use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;
use Babylcraft\WordPress\MVC\Model\IBabylonModel;
use Babylcraft\WordPress\MVC\Model\IModelFactory;

/**
 * Template class for Controller objects
 */
abstract class PluginController implements IPluginController
{
    private $viewLocationURI;
    private $modelFactory;
    protected $plugin;
    protected $controllerName;

    /**
     * Create a new Controller, passing in dependencies
     * @param PluginAPI   object for hooking into WordPress events
     */
    public function configure(
        IBabylonPlugin $plugin,
        string $controllerName
    ) {
        $this->controllerName = $controllerName;
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    protected function getModelFactory() : IModelFactory
    {
        return $this->plugin->getModelFactory();
    }

    abstract protected function registerHooks() : void;
    public function pluginActivated() : void { ; }
    public function pluginDeactivated() : void { ; }
}
