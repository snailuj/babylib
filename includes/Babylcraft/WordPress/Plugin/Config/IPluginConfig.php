<?php
namespace Babylcraft\WordPress\Plugin\Config;

interface IPluginConfig {
  public function getPluginInfoList() : IPluginInfoIterator;
}