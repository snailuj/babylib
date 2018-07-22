<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\WordPress\PluginAPI;
class PluginSingleConfig implements IPluginSingleConfig
{
    private $name;
    private $version;
    private $wpPluginsDirPath;
    private $thisPluginDirName;
    private $mvcNamespace;
    private $mvcDir;
    private $isActive;

    /*
    * @var array
    */
    private $controllerNames = [];

    public function __construct(
        string $name,
        string $wpPluginsDirPath,
        string $thisPluginDirName,
        string $mvcNamespace,
        string $version,
        bool $isActive
    ) {
        $this->name = $name;
        $this->version = $version;
        $this->wpPluginsDirPath = $wpPluginsDirPath;
        $this->thisPluginDirName = $thisPluginDirName;
        $this->mvcNamespace = $mvcNamespace;
        $this->isActive = $isActive;

        //if mvcnamespace is given, then look for controllers by convention
        if ($mvcNamespace) {
            $this->discoverControllers();
        }
    }

    public function getLibPath() : string
    {
        return "{$this->pluginDir}lib";
    }

    public function getViewPath() : string
    {
        return "{$this->mvcDir}View";
    }

    public function isActive() : bool
    {
        return $this->isActive;
    }

    public function getPluginName() : string
    {
        return $this->name;
    }

    public function getPluginVersion() : string
    {
        return $this->version;
    }

    public function getControllerNames() : array
    {
        return $this->controllerNames;
    }

    public function getPluginDir() : string
    {
        return $this->wpPluginsDirPath .'/'. $this->thisPluginDirName .'/';
    }

    public function getMVCNamespace() : string
    {
        return $this->mvcNamespace;
    }

    //returns the path to the plugin file relative to the WP Plugins dir
    public function getPluginFilePathRelative() : string
    {
        $fileName = "$this->thisPluginDirName.php";
        return "$this->thisPluginDirName/$fileName";
    }

    //simple convention-based Controller discovery
    //find all files in $pluginDir/includes/swapSlashes($mvcNamespace)/Controller
    //chop off the '.php' part
    //that's your list of Controller names
    private function discoverControllers()
    {
        $controllerFrag = str_replace("\\", "/", $this->mvcNamespace);
        $this->mvcDir = "{$this->getPluginDir()}includes/{$controllerFrag}/";
        $controllerDir = "{$this->mvcDir}Controller";
        if (!file_exists($controllerDir)) {
            throw new PluginConfigurationException(
                PluginConfigurationException::ERROR_CONTROLLER_DIR_NOT_FOUND,
                $controllerDir
            );
        }

        //iterate through PHP files in the controller dir
        foreach (glob($controllerDir."/*.php") as $fileName) {
            $fileName = substr($fileName, strrpos($fileName, "/") + 1); //chop off the path
            $this->controllerNames[] = substr($fileName, 0, -4); //chop off the '.php' part
        }
    }
}
