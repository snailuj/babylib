<?php
namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\PluginAPI;

use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

/**
 * Template class for Controller objects
 */
abstract class PluginController implements IPluginController
{
    protected $pluginAPI;
    protected $version;
    private $viewPath;
    private $viewLocationURI;

  /**
   * Create a new Controller, passing in dependencies
   * @param PluginAPI   object for hooking into WordPress events
   */
    public function configure(PluginAPI $pluginAPI, IPluginSingleConfig $pluginConfig)
    {
        $this->pluginAPI = $pluginAPI;
        $this->version = $pluginConfig->getPluginVersion();
        $this->viewPath = $pluginAPI->trailingslashit($pluginConfig->getViewPath());
        $this->selfRegisterHooks();
    }

    protected function selfRegisterHooks()
    {
        $this->viewLocationURI = null;
        $this->pluginAPI->addAction(
            'admin_init',
            function () {
                $this->viewLocationURI = $this->pluginAPI->getPathURI(
                    $this->getViewLocation(),
                    false
                );
            }
        );

        $this->registerHooks();
    }

    abstract protected function registerHooks();

    //view scripts are loaded with higher priority than "other" scripts
    //create empty fn if you don't want to enqueue any scripts
    abstract protected function enqueueViewScripts();

    //create empty fn if you don't want to enqueue any scripts
    abstract protected function enqueueOtherScripts();
    
   /*
    * Used for creating script handles etc
    */
    abstract protected function getControllerName();

    protected function createNonce(string $handle) : string
    {
        return $this->pluginAPI->createNonce($handle);
    }

    protected function localizeScript(string $handle, string $settingsName = 'settings', array $settings = [])
    {
        return $this->pluginAPI->localizeScript($handle, $settingsName, $settings);
    }

    protected function getViewPath() : string
    {
        return $this->pluginAPI->trailingslashit($this->viewPath);
    }

   /*
    * The path to your view files
    */
    protected function getViewLocation() : string
    {
        return $this->getViewPath();
    }

   /*
    * Enqueues the view JS file given by viewName. By convention the view
    * script is assumed to be in the path returned from $this->getViewLocation()
    * and named $viewName.js. jQuery is added as a dependency.
    */
    protected function enqueueViewScript(string $viewName, string $dependencies = null) : string
    {
        $handle = $this->getScriptHandle($viewName);
        $this->enqueueScript(
            $handle,
            "{$this->getViewLocationURI()}{$viewName}.js",
            $dependencies
        );

        return $handle;
    }

    protected function enqueueViewStyle(string $viewName)
    {
        $this->enqueueStyle(
            $this->getViewStyleHandle($viewName),
            "{$this->getViewLocationURI()}{$viewName}.css"
        );
    }

  /*
   * Enqueues the script given by scriptHandle. If @param uri is
   * null, the script is assumed to be in the View directory for
   * this Controller.
   *
   * @param $scriptHandle   name of script, minus the '.js' part
   */
    protected function enqueueOtherScript(
        string $scriptHandle,
        string $uri = null,
        string $dependencies = null
    ) {
        $this->enqueueScript(
            $scriptHandle,
            $uri ? $uri : "{$this->getViewLocationURI()}{$scriptHandle}.js",
            $dependencies
        );
    }

    protected function enqueueOtherStyle(
        string $styleHandle,
        string $uri = null,
        string $dependencies = null
    ) {
        $this->enqueueStyle(
            $styleHandle,
            $uri ? $uri : "{$this->getViewLocationURI()}{$styleHandle}.css",
            $dependencies
        );
    }

  /*
   * Returns a string that can be used as a handle for a view script
   */
    protected function getScriptHandle(string $viewName) : string
    {
        return "{$this->getControllerName()}_{$viewName}_js";
    }

    protected function getViewStyleHandle(string $viewName) : string
    {
        return "{$this->getControllerName()}_{$viewName}_css";
    }

    protected function enqueueScript(
        string $scriptHandle,
        string $scriptPathAndName,
        string $dependencies = null
    ) {
        wp_enqueue_script(
            $scriptHandle,
            $scriptPathAndName,
            $dependencies,
            $this->version,
            //load scripts in footer to enable last-minute localising
            $in_footer = true
        );
    }

    protected function enqueueStyle(
        string $styleHandle,
        string $stylePathAndName,
        string $dependencies = null
    ) {
        wp_enqueue_style(
            $styleHandle,
            $stylePathAndName,
            $dependencies,
            $this->version
        );
    }

    protected function getViewLocationURI() : string
    {
        if (null == $this->viewLocationURI) {
            throw new \BadMethodCallException(
                "viewLocationURI is not available yet because 'admin_init' hook has not fired"
            );
        }

        return $this->pluginAPI->trailingslashit($this->viewLocationURI);
    }
}
