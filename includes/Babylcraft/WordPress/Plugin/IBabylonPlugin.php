<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\MVC\IControllerContainer;

interface IBabylonPlugin {
  public const SERVICE_KEY_PLUGIN_API = "Babyl_Plugin_API";

  /*
   * Activate the plugin
   */
  public function activate();

  /*
   * Deactivate the plugin
   */
  public function deactivate();

  public function getPluginAPI() : PluginAPI;

  public function getControllerContainer() : IControllerContainer;
}