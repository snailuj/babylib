<?php
namespace Babylcraft\WordPress\MVC;

use Babylcraft\WordPress\Util;
use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\MVC\Controller\PluginController;
use Babylcraft\WordPress\Plugin\Config\IPluginInfo;

use Pimple\Container;

//todo rename this to ControllerFactory, it's just not ok
class ControllerContainer extends Container {
  public const SYSTEM_NOTES = "SystemNotes";

  private const KEY_CONTROLLER = "Controller";

  private $viewPath;
  public function __construct(PluginAPI $pluginAPI, Util $util, IPluginInfo $pluginInfo) {
    $controllerNames = $pluginInfo->getControllerNames();
    $util->logContent("controller names", $controllerNames);
    foreach( $controllerNames as $controllerName ) {
      $controllerClass
        = "{$pluginInfo->getMVCNamespace()}\\Controller\\{$controllerName}";
      $util->logMessage($controllerName);
      $this[$this::KEY_CONTROLLER ."_{$controllerName}"]
        = new $controllerClass( //construct from string
                  $pluginAPI, $util, $pluginInfo->getViewPath());
    }
  }
}