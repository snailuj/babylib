<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\WordPress\Util;
use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\MVC\ControllerContainer;

interface IBabylonPlugin {
  /*
   * Activate the plugin
   */
  public function activate();

  /*
   * Deactivate the plugin
   */
  public function deactivate();

  public function getPluginAPI() : PluginAPI;

  public function getUtil() : Util;

  public function getControllers() : ControllerContainer;
}