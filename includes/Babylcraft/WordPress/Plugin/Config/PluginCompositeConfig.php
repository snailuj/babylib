<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\Babylon;
use Babylcraft\WordPress\PluginAPI;

class PluginCompositeConfig extends PluginSingleConfig implements IPluginCompositeConfig
{
    /*
    * @var IPluginConfigIterator
    */
    private $pluginConfigIterator = null;

    /**
     * @var array
     */
    private $pluginInfo = null;

    //todo stop passing random arrays to this constructor :D
    public function __construct(
        string $name,
        string $wpPluginsDirPath,
        string $thisPluginDirName,
        string $mvcNamespace,
        array $pluginInfo,
        string $version,
        bool $isActive,
        int $logLevel
    ) {
        parent::__construct(
            $name,
            $wpPluginsDirPath,
            $thisPluginDirName,
            $mvcNamespace,
            $version,
            $isActive,
            $logLevel
        );

        if (null === $pluginInfo) {
            throw new \InvalidArgumentException("\$pluginInfo cannot be null");
        }

        $this->pluginInfo = $pluginInfo;
    }

    public function getIterator() : IPluginConfigIterator
    {
        if (null === $this->pluginConfigIterator) {
            $this->initIterator();
        }

        return $this->pluginConfigIterator;
    }

    private function initIterator() {
        $pluginConfigs = [];
        foreach ($this->pluginInfo as $name => $data) {
            $singleConfig = new PluginSingleConfig(
                $name,
                $this->wpPluginsDirPath,
                $data[0],
                $data[1],
                $data[2],
                PluginAPI::isBabylonPluginActive($data[0]),
                $this->logLevel
            );

            $pluginConfigs[] = $singleConfig;

            PluginAPI::infoContent($singleConfig, "PluginCompositeConfig found config for $name", __FILE__, __LINE__);
        }

        $this->pluginConfigIterator = new PluginConfigIterator($pluginConfigs);
    }
}
