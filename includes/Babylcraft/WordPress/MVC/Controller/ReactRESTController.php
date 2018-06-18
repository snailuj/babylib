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

    protected function getViewLocation(): string
    {
        return "{$this->pluginAPI->trailingslashit($this->getViewPath())}dist/";
    }
}
