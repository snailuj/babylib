<?php
namespace Babylcraft\WordPress\Plugin\Config;

class PluginConfigIterator implements IPluginConfigIterator, \Countable
{
  /*
   * @var array
   */
    private $pluginInfoList = [];

  /*
   * @var int
   */
    private $currentItemIndex = 0;

    public function __construct(array $pluginInfoList)
    {
        $this->pluginInfoList = $pluginInfoList;
    }

    public function count()
    {
        return count($this->pluginInfoList);
    }

    public function rewind()
    {
        $this->currentItemIndex = 0;
    }

    public function next()
    {
        $this->currentItemIndex++;
    }

    public function current() : IPluginSingleConfig
    {
        return $this->pluginInfoList[$this->currentItemIndex];
    }

    public function key()
    {
        return $this->currentItemIndex;
    }

    public function valid()
    {
        return ($this->currentItemIndex < $this->count());
    }
}
