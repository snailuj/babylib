<?php
namespace Babylcraft\WordPress\Plugin\Config;

class PluginInfoIterator implements IPluginInfoIterator, \Countable {

  /*
   * @var array
   */
  private $pluginInfoList = [];

  /*
   * @var int
   */
  private $currentItemIndex = 0;

  public function __construct(array $pluginInfoList) {
    foreach( $pluginInfoList as $pluginName => $pluginInfo ) {
      $this->pluginInfoList[] = new PluginInfo( $pluginName, $pluginInfo[0], $pluginInfo[1] );
    }
  }

  public function count() {
    return count($this->pluginInfoList);
  }

  public function rewind() {
    $this->currentItemIndex = 0;
  }

  public function next() {
    $this->currentItemIndex++;
  }

  public function current() : IPluginInfo {
    return $this->pluginInfoList[$this->currentItemIndex];
  }

  public function key() {
    return $this->currentItemIndex;
  }

  public function valid() {
    return ($this->currentItemIndex < $this->count());
  }
}