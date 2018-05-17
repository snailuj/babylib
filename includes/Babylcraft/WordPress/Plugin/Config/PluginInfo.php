<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\WordPress\Util;

class PluginInfo implements IPluginInfo {
  private $name;
  private $pluginDir;
  private $mvcNamespace;
  private $mvcDir;

  /*
   * @var array
   */
  private $controllerNames;

  public function __construct(string $name, string $pluginDir, string $mvcNamespace) {
    $this->name = $name;
    $this->pluginDir = $pluginDir;
    $this->mvcNamespace = $mvcNamespace;

    //find all files in $pluginDir/includes/swapSlashes($mvcNamespace)/Controller
    //chop off the '.php' part
    //that's your list of Controller names
    $controllerFrag = str_replace("\\", "/", $mvcNamespace);
    $this->mvcDir = "{$pluginDir}includes/{$controllerFrag}/";
    $controllerDir = "{$this->mvcDir}Controller";
    foreach (glob($controllerDir."/*.php") as $fileName) {
      $fileName = substr($fileName, strrpos($fileName, "/") + 1);
      Util::logMessage("filename is ". $fileName);
      $this->controllerNames[] = substr($fileName, 0, -4);
    }
  }

  public function getViewPath() : string {
    return "{$this->mvcDir}/View";
  }

  public function getPluginName() : string {
    return $this->name;
  }

  public function getControllerNames() : array {
    return $this->controllerNames;
  }

  public function getPluginDir() : string {
    return $this->pluginDir;
  }

  public function getMVCNamespace() : string {
    return $this->mvcNamespace;
  }
}