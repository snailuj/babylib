<?php

namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\Plugin\IBabylonPlugin;
use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

/**
 * Abstract class that adapts the PluginController for outputting HTML as per the usual
 * WordPress style
 */
abstract class HTMLPluginController extends PluginController
{
    /**
     * Create a new Controller, passing in dependencies
     * @param PluginAPI   object for hooking into WordPress events
     */
    public function configure(IBabylonPlugin $plugin, string $controllerName)
    {
        parent::configure($plugin, $controllerName);
    }

    //view scripts are loaded with higher priority than "other" scripts
    //create empty fn if you don't want to enqueue any scripts
    abstract protected function enqueueViewScripts();

    //create empty fn if you don't want to enqueue any scripts
    abstract protected function enqueueOtherScripts();

    abstract protected function getControllerName() : string;

    protected function selfRegisterHooks()
    {
        $this->plugin->addAction(
            'admin_enqueue_scripts',
            function () {
                $this->enqueueOtherScripts();
            },
            10
        );

        $this->plugin->addAction(
            'admin_enqueue_scripts',
            function () {
                $this->enqueueViewScripts();
            },
            99
        );
        
        parent::selfRegisterHooks();
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
                $this->plugin->error("Unknown error code $errorCode", __FILE__, __LINE__);
                $message .= "unknown error $errorCode";
        }

        wp_send_json_error(["message" => $message]);
    }

    protected function enqueueViewStyle(string $styleName) : string 
    {
        return $this->plugin->enqueueViewStyle($this->getControllerName(), $styleName);
    }

    protected function enqueueViewScript(string $viewName) : string 
    {
        return $this->plugin->enqueueViewScript($this->getControllerName(), $viewName);
    }

    protected function getViewScriptHandle(string $viewName) : string
    {
        return $this->plugin->getScriptHandle($this->getControllerName(), $viewName);
    }

    protected function getViewMarkup(string $viewName) : string
    {
        return $this->plugin->getViewMarkup($this->getControllerName(), $viewName);
    }
}