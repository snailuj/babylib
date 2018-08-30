<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\WordPress\MVC\Model\IModelFactory;


interface IPluginCompositeConfig extends IPluginSingleConfig
{
    public function getIterator() : IPluginConfigIterator;
}
