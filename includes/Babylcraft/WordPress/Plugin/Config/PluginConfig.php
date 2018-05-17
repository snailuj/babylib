<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\WordPress\Plugin\Config\PluginInfoIterator;

class PluginConfig implements IPluginConfig {
  /*
   * @var IPluginIterator
   */
  private $plugins;
  public function __construct(array $pluginInfoList) {
    $this->plugins = new PluginInfoIterator($pluginInfoList);
  }

  public function getPluginInfoList() : IPluginInfoIterator {
    return $this->plugins;
  }
}