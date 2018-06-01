<?php
namespace Babylcraft\WordPress\Plugin\Config;

class PluginCompositeConfig extends PluginSingleConfig implements IPluginCompositeConfig
{

  /*
   * @var IPluginConfigIterator
   */
    private $plugins;

  //todo stop passing random arrays to these constructors :D
    public function __construct(
        string $name,
        string $pluginDir,
        string $mvcNamespace,
        array $pluginInfoList = []
    ) {

        parent::__construct($name, $pluginDir, $mvcNamespace);

        $pluginConfigList = [];
        foreach ($pluginInfoList as $pluginName => $pluginInfo) {
            $pluginConfigList[]
            = new PluginSingleConfig($pluginName, $pluginInfo[0], $pluginInfo[1]);
        }

        $this->plugins = new PluginConfigIterator($pluginConfigList);
    }

    public function getPluginConfigList() : IPluginConfigIterator
    {
        return $this->plugins;
    }
}
