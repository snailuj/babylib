<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\Babylon;
use Babylcraft\WordPress\MVC\ControllerContainer;
use Babylcraft\WordPress\Plugin\Config\IPluginCompositeConfig;

interface IPluginComposite extends IBabylonPlugin {

  /*
   * Put things into the bag of holding
   */
  public function hydrate(Babylon $container, IPluginCompositeConfig $config);

  public function getPlugins() : IPluginIterator;

  public function getControllersForPlugin(string $forPlugin) : ControllerContainer;
}