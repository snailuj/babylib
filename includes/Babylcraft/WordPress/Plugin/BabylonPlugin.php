<?php
namespace Babylcraft\WordPress\Plugin;

use Pimple\Container;

use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\MVC\IControllerContainer;
use Babylcraft\WordPress\MVC\ControllerContainerException;

use Babylcraft\WordPress\MVC\Controller\IPluginController;

use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;
use Babylcraft\WordPress\Plugin\Config\PluginConfigurationException;

abstract class BabylonPlugin implements IBabylonPlugin, IControllerContainer
{
    use PluginAPI;

    const KEY_CONTROLLER = "Controller_";

    /**
     * @var IPluginSingleConfig
     */
    protected $config;

    /**
     * @var Container
     */
    private $controllers;

    /**
     * @var string
     */
    private $pluginURI;

    /**
     * @var string
     */
    private $viewURI;
    public function hydrate(IPluginSingleConfig $config = null)
    {
        if (!$config) {
            $this->pluginAPI->warn("hydrating BabylonPlugin with null config in BabylonPlugin::hydrate()");
            return;
        }

        $this->config = $config;
        $this->registerSymlinkPlugin($this->config->getPluginDir()); //works if plugin isn't symlinked too
        $this->registerActivationHook(
            $this->config->getPluginFilePathRelative(),
            function() {
                $this->activate();
            }
        );

        $this->registerDeactivationHook(
            $this->config->getPluginFilePathRelative(),
            function() {
                $this->deactivate();
            }
        );

        if (!$config->isActive()) {
            return; //play nicely if not activated
        }

        $this->doHydrate();
    }

    protected function doHydrate()
    {
        $controllers = new Container();
        $controllerNames = $this->config->getControllerNames();
        $mvcNamespace = $this->config->getMVCNamespace();
        $mvcNamespace = $mvcNamespace ? "{$mvcNamespace}" : "";
        foreach ($controllerNames as $controllerName) {
            $controllerClass = "{$mvcNamespace}\\Controller\\{$controllerName}";
            if (!class_exists($controllerClass)) {
                throw new ControllerContainerException(
                    ControllerContainerException::ERROR_NO_SUCH_CLASS,
                    $controllerClass
                );
            }
            
            //construct from string
            $controller = new $controllerClass();
            if (!($controller instanceof IPluginController)) {
                throw new ControllerContainerException(
                    ControllerContainerException::ERROR_NOT_A_CONTROLLER,
                    $controllerClass
                );
            }

            //can't cast to a class in PHP :'(
            $controller->configure($this, substr($controllerName, 0, -10)); //chop off the 'Controller' suffix
            $controllers[$this::KEY_CONTROLLER . $controllerName] = $controller;
        }
    }

    public function activate()
    {
        $this->debugContent($_SERVER['REQUEST_URI'], $this->config->getPluginName() ."::activate() called. Request uri = ");

        if ($this->config->isActive()) {
            throw new PluginConfigurationException(PluginConfigurationException::ERROR_PLUGIN_ALREADY_ACTIVE, $this);
        }

        $this->doActivate();

        
        $this->info($this->config->getPluginName() ." activated ");
    }

    public function deactivate()
    {
        $this->debugContent($_SERVER['REQUEST_URI'], $this->config->getPluginName() ."::deactivate() called. Request uri = ");

        if (!$this->config->isActive()) {
            throw new PluginConfigurationException(PluginConfigurationException::ERROR_PLUGIN_ALREADY_INACTIVE, $this);
        }

        $this->doDeactivate();

        $this->info($this->config->getPluginName() ." deactivated ");
    }

    abstract protected function doActivate();
    abstract protected function doDeactivate();

    public function getVersion() : string 
    {
        return $this->config->getPluginVersion();
    }

    public function getPluginURI() : string 
    {
        if (!$this->pluginURI) {
            $this->pluginURI = $this->trailingslashit(
                $this->getPathURI($this->config->getPluginDir(), false));
        }

        return $this->pluginURI;
    }

    private $libURI;
    public function getLibURI() : string 
    {
        if (!$libURI) {
            $libURI = $this->getPathURI($this->plugin->getLibPath(), false);
        }

        return $libURI;
    }
    
    /*
    * Returns the full path and name of the view markup/PHP script.
    * This can then be require_once'd by controller functions to render
    * a given view.
    *
    * By convention the view markup is assumed to be in the path returned
    * from $this->getViewLocation() and named $viewName.php
    *
    * @param $viewName   The view name to get markup for
    */
    public function getViewMarkup(string $controllerName, string $viewName) : string
    {
        return "{$this->getViewLocation($controllerName, $viewName)}${viewName}.php";
    }

    /*
    * Enqueues the view JS file given by viewName. By convention the view
    * script is assumed to be in the path returned from $this->getViewLocation()
    * and named $viewName.js. If you don't follow that convention, the call
    * will fail.
    */
    public function enqueueViewScript(string $controllerName, string $viewName, string $dependencies = null) : string
    {
        $scriptHandle = $this->getScriptHandle($controllerName, $viewName);
        $this->enqueueScript(
            $scriptHandle, 
            "{$this->getViewLocationURI($controllerName, $viewName)}{$viewName}.js",
            $dependencies
        );

        return $scriptHandle;
    }

    public function enqueueLibScript(string $libName, string $dependencies = null) : string
    {
        $libScriptHandle = $this->getScriptHandle("libs", $libName);
        $this->enqueueScript(
            $libScriptHandle,
            "{$this->getLibURI()}{$libName}/{$libName}.js",
            $dependencies
        );

        return $libScriptHandle;
    }

    /**
     * Enqueues the bundled JS file (e.g. one created via webpack) from the built view location.
     * If getBuiltViewLocationURI() returns null, throws \BadMethodCallException.
     * 
     * @param string $scriptName    The name of the script, minus '.js' suffix
     * @throws \BadMethodCallException  If getBuiltViewLocationURI() returns null
     * @return string   The generated handle for the enqueued script
     */
    protected function enqueueBundleScript(string $controllerName, string $scriptName) : string
    {
        $handle = "{$controllerName}_{$scriptName}_bundle";
        $this->enqueueOtherScript($handle, "{$this->getBuiltViewLocationURI()}{$scriptName}.js");

        return $handle;
    }

    protected function getBuiltViewLocationURI() : string
    {
        return null;
    }

    /*
    * Enqueues the script given by scriptHandle.
    *
    * @param $scriptHandle   name of script, minus the '.js' part
    */
    public function enqueueOtherScript(
        string $scriptHandle,
        string $uri,
        string $dependencies = null
    ) : string {
        $this->enqueueScript($scriptHandle, $uri, $dependencies);

        return $scriptHandle;
    }

    public function enqueueViewStyle(string $controllerName, string $viewName)
    {
        $styleHandle = $this->getViewStyleHandle($controllerName, $viewName);
        $this->enqueueStyle(
            $styleHandle,
            "{$this->getViewLocationURI($controllerName, $viewName)}{$viewName}.css"
        );

        return $styleHandle;
    }

    public function enqueueLibStyle(string $libName)
    {
        $libStyleHandle = $this->getViewStyleHandle("libs", $libName);
        $this->enqueueStyle($libStyleHandle,
            "{$this->getLibLocationURI()}{$libName}/{$libName}.css"
        );

        return $libStyleHandle;
    }

    public function enqueueOtherStyle(
        string $styleHandle,
        string $uri,
        string $dependencies = null
    ) : string {
        $this->enqueueStyle($styleHandle, $uri, $dependencies);

        return $styleHandle;
    }

    protected function enqueueScript(
        string $scriptHandle,
        string $scriptPathAndName,
        string $dependencies = null
    ) { //TODO put this in PluginAPI
        wp_enqueue_script(
            $scriptHandle,
            $scriptPathAndName,
            $dependencies,
            $this->getPluginVersion(),
            //load scripts in footer to enable last-minute localising
            $in_footer = true
        );
    }

    protected function enqueueStyle(
        string $styleHandle,
        string $stylePathAndName,
        string $dependencies = null
    ) { //TODO put this in PluginAPI
        wp_enqueue_style(
            $styleHandle,
            $stylePathAndName,
            $dependencies,
            $this->getPluginVersion()
        );
    }

    protected function getViewURI() : string
    {
        if (!$this->viewURI) {
            $this->viewURI = $this->trailingslashit(
                $this->getPathURI(
                    $this->getViewPath(),
                    false
                )
            );
        }
    }

    private function getViewLocation(string $controllerName, string $viewName) : string
    {
        return $this->trailingslashit("{$this->getViewPath()}{$controllerName}");
    }

    private function getViewLocationURI(string $controllerName, string $viewName) : string
    {
        return $this->getPathURI($this->getViewLocation($controllerName, $viewName), false);
    }

    /*
    * Returns a string that can be used as a handle for a view script
    */
    public function getScriptHandle(string $controllerName, string $viewName) : string
    {
        return "{$controllerName}_{$viewName}_js";
    }

    public function getViewStyleHandle(string $controllerName, string $viewName) : string
    {
        return "{$controllerName}_{$viewName}_css";
    }

    public function isActive() : bool
    {
        return $this->config->isActive();
    }

    public function getPluginName() : string
    {
        return $this->config->getPluginName();
    }

    public function getPluginVersion() : string 
    {
        return $this->config->getPluginVersion();
    }

    public function getLibPath() : string 
    {
        return $this->trailingslashit($this->config->getLibPath());
    }

    public function getViewPath() : string 
    {
        return $this->trailingslashit($this->config->getViewPath());
    }
}