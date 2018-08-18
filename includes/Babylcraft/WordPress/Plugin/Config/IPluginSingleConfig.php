<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\WordPress\MVC\Controller\Config\IControllerConfig;

interface IPluginSingleConfig extends IControllerConfig
{
    public function isActive() : bool;
    public function getLogLevel() : int;
    public function getPluginName() : string;
    public function getPluginVersion() : string;
    public function getLibPath() : string;
    public function getViewPath() : string;
    public function getPluginDir() : string;
    public function getPluginFilePathRelative() : string;
}
