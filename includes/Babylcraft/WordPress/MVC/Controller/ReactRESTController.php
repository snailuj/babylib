<?php
namespace Babylcraft\WordPress\MVC\Controller;

/**
 * Abstract class that adapts the PluginController for exposing REST API
 * endpoints
 */
abstract class ReactRESTController extends PluginController
{
    const AJAX_BASE = "/wp-json";

    abstract protected function getAjaxBase() : string;

    /**
     * Uses WP to enqueue $scriptName.js from the built view location.
     *
     * @param string $scriptName    The name of the script minus file extension. '.js' will be added
     * @return string   The generated script handle
     */
    protected function enqueueReactScript(string $scriptName) : string
    {
        $handle = $this->getScriptHandle($scriptName);
        $this->enqueueOtherScript($handle, "{$this->getBuiltViewLocationURI()}{$scriptName}.js");

        return $handle;
    }

    protected function getBuiltViewLocationURI() : string
    {
        return "{$this->getViewLocationURI()}dist/";
    }
}
