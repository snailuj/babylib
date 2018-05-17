<?php
namespace Babylcraft\WordPress;

use Babylcraft\Babylon;
use Babylcraft\WordPress\MVC\ControllerContainer;
use Babylcraft\WordPress\Plugin\Config\IPluginConfig;

interface IBabylonPlugin {
  /*
   * Put things into the bag of holding
   */
  public function hydrate(Babylon $container, IPluginConfig $config);

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

  public function getControllers(string $forPlugin) : ControllerContainer;
}