<?php
namespace Babylcraft\WordPress\Plugin\Config;

class PluginSingleConfig implements IPluginSingleConfig {
  private $name;
  private $pluginDir;
  private $mvcNamespace;
  private $mvcDir;

  /*
   * @var array
   */
  private $controllerNames = [];

  public function __construct(string $name, string $pluginDir, string $mvcNamespace) {
    $this->name = $name;
    $this->pluginDir = $pluginDir;
    $this->mvcNamespace = $mvcNamespace;

    //if mvcnamespace is given, then look for controllers by convention
    if ($mvcNamespace) {
      $this->discoverControllers();
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

  //simple convention-based Controller discovery
  //find all files in $pluginDir/includes/swapSlashes($mvcNamespace)/Controller
  //chop off the '.php' part
  //that's your list of Controller names
  private function discoverControllers() {
    $controllerFrag = str_replace("\\", "/", $this->mvcNamespace);
    $this->mvcDir = "{$this->pluginDir}includes/{$controllerFrag}/";
    $controllerDir = "{$this->mvcDir}Controller";
    if (!file_exists($controllerDir)) {
      throw new PluginConfigurationException(
        PluginConfigurationException::ERROR_CONTROLLER_DIR_NOT_FOUND, $controllerDir);
    }

    //iterate through PHP files in the controller dir
    foreach (glob($controllerDir."/*.php") as $fileName) {
      $fileName = substr($fileName, strrpos($fileName, "/") + 1); //chop off the path
      $this->controllerNames[] = substr($fileName, 0, -4); //chop off the '.php' part
    }
  }
}