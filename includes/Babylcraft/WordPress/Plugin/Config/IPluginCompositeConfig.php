<?php
namespace Babylcraft\WordPress\Plugin\Config;

interface IPluginCompositeConfig extends IPluginSingleConfig {
  public function getPluginConfigList() : IPluginConfigIterator;
}