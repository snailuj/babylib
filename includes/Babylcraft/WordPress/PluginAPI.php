<?php
namespace Babylcraft\WordPress;

//refactor me: core classes should not depend on specific plugins
use Babylcraft\Plugins\Bookings\BabylonBookings;
use DownShift\Wordpress\EventEmitterInterface;

//todo refactor to use interfaces so client classes are more testable

/**
 * Class PluginAPI
 *
 * Handles interactions with the WordPress Plugins API
 *
 * @package Babylcraft\WordPress
 */
class PluginAPI {
  private $eventEmitter;

  /**
   * @return EventEmitterInterface
   */
  public static function getHookManager() : PluginAPI {
    assert(
      function_exists('babylGetServices'),
      AssertionError('global fn babylGetServices() does not exist'));

    return babylGetServices()[BabylonBookings::PLUGIN_API];
  }

  //don't call this function except when bootstrapping your plugin
  //at which point you should stuff it into Pimple
  //use getHookManager() instead
  public function __construct(EventEmitterInterface $eventEmitter) {
    $this->eventEmitter = $eventEmitter;
  }

  /**
   * Add a hook through plugins API service
   *
   * @param string $hookName  Name of the hook
   * @param $hookFn           The hook function to run
   * @param int $priority
   * @param int $acceptedArgs
   */
  public function addAction(
                  string $hookName,
                  $hookFn,
                  int $priority = 10,
                  int $acceptedArgs = 1 ) {
    $this->eventEmitter->on(
      $hookName, $hookFn, $priority, $acceptedArgs);
  }

  /**
   * Add a filter through plugins API service
   *
   * @param string $hookName  Name of the hook
   * @param $filterFn         The filter function to run
   * @param int $priority
   * @param int $acceptedArgs
   */
  public function addFilter(
                  string $hookName,
                  $filterFn,
                  int $priority = 10,
                  int $acceptedArgs = 1) {
    $this->eventEmitter->filter(
      $hookName, $filterFn, $priority, $acceptedArgs);
  }
}