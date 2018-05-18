<?php
namespace Babylcraft\WordPress\Plugin\Config;

interface IPluginConfigIterator extends \Iterator {
  public function current() : IPluginSingleConfig;
}