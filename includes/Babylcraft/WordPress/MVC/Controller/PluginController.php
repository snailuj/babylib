<?php
namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\PluginAPI;

use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

/**
 * Template class for Controller objects with factory
 * method for constructing from instance of IPluginSingleConfig
 */
abstract class PluginController
{
    protected $pluginAPI;
    private $libPath;
    private $viewPath;
    private $libLocationURI;
    private $viewLocationURI;

  //todo refactor so this class doesn't need to know details of how
  //its constructed and can just be new()ed.

  /**
   * Create a new Controller, passing in dependencies
   * @param PluginAPI   object for hooking into WordPress events
   */
    public function __construct(
        PluginAPI $pluginAPI,
        string $viewPath,
        string $libPath
    ) {
        $this->pluginAPI = $pluginAPI;
        $this->libPath = $libPath;
        $this->viewPath = $viewPath;
        $this->selfRegisterHooks($pluginAPI);
    }

    private function selfRegisterHooks()
    {
        $this->viewLocationURI = $this->libLocationURI = null;
        $this->pluginAPI->addAction(
            'admin_init',
            function () {
                $this->pluginAPI->logContent("Lib Path", $this->libPath);
                $this->libLocationURI = $this->pluginAPI->getPathURI(
                    "$this->libPath",
                    false
                );

                $this->pluginAPI->logContent("Lib URI", $this->libLocationURI);

                $this->viewLocationURI = $this->pluginAPI->getPathURI(
                    $this->getViewLocation(),
                    false
                );
            }
        );

        $this->pluginAPI->addAction(
            'admin_enqueue_scripts',
            function () {
                $this->enqueueOtherScripts();
            },
            10
        );

        $this->pluginAPI->addAction(
            'admin_enqueue_scripts',
            function () {
                $this->enqueueViewScripts();
            },
            99
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
   * Must override. View files are by convention assumed to be located in
   * the $this->viewPath/$this->getControllerName()/ directory. This convention
   * is used by various functions of this class to get view markup or js files
   * for require_once'ing or wp_enqueue_script'ing.
   */
    abstract protected function getControllerName() : string;

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
    protected function getViewMarkupFile(string $viewName) : string
    {
        return "{$this->getViewLocation()}/${viewName}.php";
    }

  /*
   * Enqueues the view JS files given by viewName. By convention the view
   * script is assumed to be in the path returned from $this->getViewLocation()
   * and named $viewName.js. jQuery is added as a dependency.
   */
    protected function enqueueViewScript(string $viewName)
    {
        $this->enqueueScript(
            $this->getScriptHandle($viewName),
            "{$this->getViewLocationURI()}{$viewName}.js",
            'jquery'
        );
    }

    protected function enqueueLibScript(string $libName, string $dependencies)
    {
        $this->enqueueScript(
            $this->getScriptHandle($libName),
            "{$this->getLibLocationURI()}{$libName}/{$libName}.js",
            $dependencies
        );
    }

    protected function enqueueViewStyle(string $viewName)
    {
        $this->enqueueStyle(
            $this->getViewStyleHandle($viewName),
            "{$this->getViewLocationURI()}{$viewName}.css"
        );
    }

    protected function enqueueLibStyle(string $libName)
    {
        $this->enqueueStyle(
            $this->getViewStyleHandle($libName),
            "{$this->getLibLocationURI()}{$libName}/{$libName}.css"
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

  /*
   * Returns a string that can be used as a handle for the view script
   * in calls to wp_enqueue_script(), wp_localize_script() and
   * check_admin_referer()
   */
    protected function getScriptHandle(string $viewName) : string
    {
        return "{$this->getControllerName()}{$viewName}_js";
    }

    protected function getViewStyleHandle(string $viewName) : string
    {
        return "{$this->getControllerName()}{$viewName}_css";
    }

    private function enqueueScript(
        string $scriptHandle,
        string $scriptPathAndName,
        string $dependencies = null
    ) {
        wp_enqueue_script(
            $scriptHandle,
            $scriptPathAndName,
            $dependencies,
            //Plugin::VERSION,
            "0.0.1", //todo update me
            //load scripts in footer to enable last-minute localising
            $in_footer = true
        );
    }

    private function enqueueStyle(
        string $styleHandle,
        string $stylePathAndName,
        string $dependencies = null
    ) {
        wp_enqueue_style(
            $styleHandle,
            $stylePathAndName,
            $dependencies,
            //Plugin::VERSION,
            "0.0.1" //todo refactor this to pull it from plugin
        );
    }

    protected function getLibLocationURI() : string
    {
        if (null == $this->libLocationURI) {
            throw new \BadMethodCallException(
                "viewLocationURI is not available yet because 'admin_init' hook has not fired"
            );
        }

        return $this->libLocationURI;
    }

    private function getViewLocation() : string
    {
        return "$this->viewPath/{$this->getControllerName()}";
    }

    protected function getViewLocationURI() : string
    {
        if (null == $this->viewLocationURI) {
            throw new \BadMethodCallException(
                "viewLocationURI is not available yet because 'admin_init' hook has not fired"
            );
        }

        return $this->viewLocationURI;
    }

    protected const ERROR_NOT_PERMITTED = 1;
    protected const ERROR_MISSING_DATA_FROM_CLIENT = 2;
    protected function sendJSONError(string $actionName, int $errorCode)
    {
        $message = "$actionName: ";
        switch ($errorCode) {
            case Controller::ERROR_NOT_PERMITTED:
                $message .= 'not permitted for user';
                break;
            case Controller::ERROR_MISSING_DATA_FROM_CLIENT:
                $message .= 'missing data from request';
                break;
            default:
                $pluginAPI->logMessage("Unknown error code $errorCode", __FILE__, __LINE__);
                $message .= "unknown error $errorCode";
        }

        wp_send_json_error(["message" => $message]);
    }
}
