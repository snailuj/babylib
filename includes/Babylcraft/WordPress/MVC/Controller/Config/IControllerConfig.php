<?php
namespace Babylcraft\WordPress\MVC\Controller\Config;


interface IControllerConfig
{
    function getMVCNamespace() : string;
    function getControllerNames() : array;
    function hasDefaultModelFactory() : bool;
    function getModelFactoryClassName() : string;
}
?>