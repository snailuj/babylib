<?php
namespace Babylcraft\WordPress\MVC;

use Pimple\Container;
use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\MVC\Controller\IPluginController;
use Babylcraft\WordPress\Plugin\Config\IPluginSingleConfig;

class ControllerContainer implements IControllerContainer
{
    const KEY_CONTROLLER = "Controller";

  /*
   * @var Container   Bag for holding Controller instances
   */
    private $container;

  /*
   * @var string  Path to directory containing View assets
   */
    private $viewPath;
    public function __construct(
        PluginAPI $pluginAPI,
        IPluginSingleConfig $pluginInfo
    ) {
        $container = new Container();

        $controllerNames = $pluginInfo->getControllerNames();
        $mvcNamespace = $pluginInfo->getMVCNamespace();
        $mvcNamespace = $mvcNamespace ? "{$mvcNamespace}" : "";
        foreach ($controllerNames as $controllerName) {
            $controllerClass
            = "{$mvcNamespace}\\Controller\\{$controllerName}";
            if (!class_exists($controllerClass)) {
                throw new ControllerContainerException(
                    ControllerContainerException::ERROR_NO_SUCH_CLASS,
                    $controllerClass
                );
            }
            
            //construct from string
            $controller = new $controllerClass();
            if (!($controller instanceof IPluginController)) {
                throw new ControllerContainerException(
                    ControllerContainerException::ERROR_NOT_A_CONTROLLER,
                    $controllerClass
                );
            }

            //can't cast to a class in PHP :(
            $controller->configure($pluginAPI, $pluginInfo);
            $container[$this::KEY_CONTROLLER ."_{$controllerName}"] =
                $controller;
        }
    }
}
