<?php
namespace Babylcraft\WordPress\Plugin\Config;

interface IPluginSingleConfig {
  public function getPluginName() : string;
  public function getControllerNames() : array;
  public function getViewPath() : string;
  public function getPluginDir() : string;
  public function getMVCNamespace() : string;
}