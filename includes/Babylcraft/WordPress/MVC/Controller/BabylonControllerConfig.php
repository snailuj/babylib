<?php
namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\WordPress\Plugin\Config\IPluginInfo;

class ControllerConfigList implements IControllerConfigList, Countable {
/*
   * @var array
   */
  private $items = [];

  /*
   * @var int
   */
  private $currentItemIndex = 0;

  public function __construct(IPluginInfo $pluginInfo) {
    $configs = $pluginInfo->getControllerConfigList();
    foreach( $configs as $controllerConfig ) {
      $this->items[] = new PluginInfo( $name, $item[0], $item[1] );
    }
  }

  public function count() {
    return count($this->items);
  }

  public function rewind() {
    $this->currentItemIndex = 0;
  }

  public function next() {
    $this->currentItemIndex++;
  }

  public function current() : IPluginInfo {
    return $this->items[$this->currentItemIndex];
  }

  public function key() {
    return $this->currentItemIndex;
  }

  public function valid() {
    return ($this->currentItemIndex < $this->count());
  }
}