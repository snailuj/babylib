<?php
namespace Babylcraft\WordPress\MVC\Controller\Config;

interface IControllerConfig
{
    public function getMVCNamespace() : string;
    public function getControllerNames() : array;
}
?>