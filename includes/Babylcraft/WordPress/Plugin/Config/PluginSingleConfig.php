<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\WordPress\PluginAPI;
class PluginSingleConfig implements IPluginSingleConfig
{
    protected $name;
    protected $version;
    protected $wpPluginsDirPath;
    protected $thisPluginDirName;
    protected $mvcNamespace;
    protected $mvcDir;
    protected $isActive;
    protected $logLevel;

    /*
    * @var array
    */
    protected $controllerNames = null;

    public function __construct(
        string $name,
        string $wpPluginsDirPath,
        string $thisPluginDirName,
        string $mvcNamespace,
        string $version,
        bool $isActive,
        int $logLevel
    ) {
        $this->name =               $name;
        $this->version =            $version;
        $this->wpPluginsDirPath =   $wpPluginsDirPath;
        $this->thisPluginDirName =  $thisPluginDirName;
        $this->mvcNamespace =       $mvcNamespace;
        $this->isActive =           $isActive;
        $this->logLevel =           $logLevel;
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

    public function getLogLevel() : int
    {
        return $this->logLevel;
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
        if (null === $this->controllerNames) {
            $this->discoverControllers();
        }

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
        $this->controllerNames = [];
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
