<?php

namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

/**
 * Abstract class that adapts the PluginController for outputting HTML as per the usual
 * WordPress style
 */
abstract class HTMLPluginController extends PluginController
{
    private $libPath;
    private $libLocationURI;

    /**
     * Create a new Controller, passing in dependencies
     * @param PluginAPI   object for hooking into WordPress events
     */
    public function configure(
        PluginAPI $pluginAPI,
        IPluginSingleConfig $pluginConfig
    ) {
        parent::configure($pluginAPI, $pluginConfig);
        $this->libPath = $pluginAPI->trailingslashit($pluginConfig->getLibPath());
    }

    protected function selfRegisterHooks()
    {
        $this->pluginAPI->addAction(
            'admin_init',
            function () {
                $this->libLocationURI = $this->pluginAPI->trailingslashit($this->pluginAPI->getPathURI(
                    $this->libPath,
                    false
                ));
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
        
        parent::selfRegisterHooks();
    }

    //TODO do we need multiple View directories?
    //Only if we might have multiple Controllers in a plugin
    //If not remove this override
    protected function getViewLocation() : string
    {
        return $this->pluginAPI->trailingslashit("{$this->getViewPath()}{$this->getControllerName()}");
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
    protected function getViewMarkupFile(string $viewName) : string
    {
        return "{$this->getViewLocation()}${viewName}.php";
    }

    protected function enqueueLibScript(string $libName, string $dependencies = null)
    {
        $this->enqueueScript(
            $this->getScriptHandle($libName),
            "{$this->getLibLocationURI()}{$libName}/{$libName}.js",
            $dependencies
        );
    }
    
    protected function enqueueLibStyle(string $libName)
    {
        $this->enqueueStyle(
            $this->getViewStyleHandle($libName),
            "{$this->getLibLocationURI()}{$libName}/{$libName}.css"
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

    const ERROR_NOT_PERMITTED = 1;
    const ERROR_MISSING_DATA_FROM_CLIENT = 2;
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
