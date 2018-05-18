<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

interface IPluginSingle extends IBabylonPlugin {
  /*
   * Hydrate self with given info
   */
  public function hydrate(Babylon $container, IPluginSingleConfig $config);
}