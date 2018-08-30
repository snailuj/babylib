<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\WordPress\MVC\Controller\Config\IControllerConfig;

interface IPluginSingleConfig extends IControllerConfig
{
    function isActive() : bool;
    function getPluginName() : string;
    function getPluginVersion() : string;
    function getLibPath() : string;
    function getViewPath() : string;
    function getPluginDir() : string;
    function getPluginFilePathRelative() : string;
}
