<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\Babylon;
use Babylcraft\WordPress\PluginAPI;
use Babylcraft\WordPress\MVC\Model\IModelFactory;
use Babylcraft\WordPress\MVC\Model\Impl\ModelFactory;

class PluginCompositeConfig extends PluginSingleConfig implements IPluginCompositeConfig
{
    /**
     * @var IPluginConfigIterator Used for iterating over the {@link IPluginSingleConfig} objects
     * that are contained in this PluginCompositeConfig
     */
    private $pluginConfigIterator = null;

    /**
     * @var array Raw config info before it has been hydrated into {@link IPluginSingleConfig}s
     */
    private $pluginInfo = null;

    /**
     * @var IModelFactory The default IModelFactory implementation to use if a plugin
     * doesn't define its own
     */
    private $defaultModelFactory = null;

    //todo stop passing random arrays to this constructor :D
    public function __construct(
        string $name,
        string $wpPluginsDirPath,
        string $thisPluginDirName,
        string $mvcNamespace,
        array $pluginInfo,
        string $version,
        bool $isActive
    ) {
        parent::__construct(
            $name,
            $wpPluginsDirPath,
            $thisPluginDirName,
            $mvcNamespace,
            $version,
            $isActive
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

    public function getDefaultModelFactory(): IModelFactory
    {
        return $this->defaultModelFactory;
    }

    private function initIterator() {
        $subPluginConfigs = [];
        foreach ($this->pluginInfo as $subPluginName => $subPluginInfo) {
            $subPluginConfig = new PluginSingleConfig(
                $subPluginName,
                $this->wpPluginsDirPath,
                $subPluginInfo['pluginDir'],
                $subPluginInfo['mvcNamespace'],
                $subPluginInfo['version'],
                PluginAPI::isBabylonPluginActive($subPluginInfo['pluginDir']),
                $subPluginInfo['modelFactory'] ?? ''
            );

            $subPluginConfigs[] = $subPluginConfig;

            PluginAPI::infoContent($subPluginConfig, "PluginCompositeConfig found config for $subPluginName", __FILE__, __LINE__);
        }

        $this->pluginConfigIterator = new PluginConfigIterator($subPluginConfigs);
    }
}
