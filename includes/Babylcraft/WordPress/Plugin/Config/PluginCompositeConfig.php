<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\Babylon;
use Babylcraft\WordPress\PluginAPI;

class PluginCompositeConfig extends PluginSingleConfig implements IPluginCompositeConfig
{
    /*
    * @var IPluginConfigIterator
    */
    private $pluginConfigIterator;

    //todo stop passing random arrays to these constructors :D
    public function __construct(
        string $name,
        string $wpPluginsDir,
        string $thisPluginDirName,
        string $mvcNamespace,
        array $pluginInfoList,
        string $version,
        Babylon $babylon
    ) {
        parent::__construct(
            $name,
            $wpPluginsDir,
            $thisPluginDirName,
            $mvcNamespace,
            $version,
            true //assume active else we wouldn't be here
        );

        $pluginConfigList = [];
        foreach ($pluginInfoList as $pluginName => $pluginInfo) {
            $pluginConfigList[] = new PluginSingleConfig(
                $pluginName,
                $wpPluginsDir,
                $pluginInfo[0],
                $pluginInfo[1],
                $pluginInfo[2],
                PluginAPI::isBabylonPluginActive($pluginInfo[0])
            );
        }

        $this->pluginConfigIterator = new PluginConfigIterator($pluginConfigList);
    }

    public function getPluginConfigList() : IPluginConfigIterator
    {
        return $this->pluginConfigIterator;
    }
}
