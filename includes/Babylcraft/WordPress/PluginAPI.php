<?php
namespace Babylcraft\WordPress;

//refactor me: core classes should not depend on specific plugins
use Babylcraft\Plugin\IBabylonPlugin;
use DownShift\Wordpress\EventEmitterInterface;

//todo refactor to use interfaces so client classes are more testable

/**
 * Class PluginAPI
 *
 * Handles interactions with the WordPress Plugin API
 *
 * @package Babylcraft\WordPress
 */
class PluginAPI
{
    private $eventEmitter;

  //don't call this function except when bootstrapping your plugin
  //at which point you should stuff it into Pimple
  //use getHookManager() instead
    public function __construct(EventEmitterInterface $eventEmitter)
    {
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
        int $acceptedArgs = 1
    ) {
        $this->eventEmitter->on(
            $hookName,
            $hookFn,
            $priority,
            $acceptedArgs
        );
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
        int $acceptedArgs = 1
    ) {
        $this->eventEmitter->filter(
            $hookName,
            $filterFn,
            $priority,
            $acceptedArgs
        );
    }

    public function isAdminDashboard() : bool
    {
        if (function_exists('get_current_screen')) {
            $adminPage = get_current_screen();
        }

        return $adminPage->base == 'dashboard';
    }

  /*
   * Converts the given path into a web-accessible URI.
   *
   * @param $useParent  Whether to ascend to the parent dir or not.
   *                    Normally WordPress assumes that the path
   *                    points to a file, so would return the parent
   *                    directory of the final path element. By passing
   *                    $useParent = false, you can get the URI to the
   *                    final path element itself (this is useful when
   *                    your $path points to the directory you are
   *                    interested in)
   */
    public function getPathURI(string $path, bool $useParent) : string
    {
      //plugin_dir_url always takes the parent dir of whatever's passed
      //in so use placeholder text to stay in the given dir if $useParent = false
        return $useParent ?
        plugin_dir_url($path) :
        plugin_dir_url("$path/placeholdertext");
    }

  //avoid calling these statically except for debugging purposes
    public static function logContent(string $message, $content, $fileName = '', $lineNum = '')
    {
        error_log("TEST");
        if (true == WP_DEBUG) {
            if (is_array($content) || is_object($content)) {
                PluginAPI::logMessage("{$message}: \n". print_r($content, true), $fileName, $lineNum);
            } else {
                PluginAPI::logMessage("{$message}: \n{$content}");
            }
        }
    }

    public static function logMessage(string $message, $fileName = '', $lineNum = '')
    {
        $date = new \DateTime("now", new \DateTimeZone("Pacific/Auckland"));

        error_log(
            "\n\n-------Babylon begin-------"
            ."\n{$date->format('d/m/Y h:i:s a')}: $message\n"
            .($fileName ? "at $fileName" : '') . ($lineNum ? ": $lineNum" : '') ."\n"
            ."-------Babylon end-------\n\n"
        );
    }

  /**
   * @return EventEmitterInterface
   */
  // private static function getHookManager() : PluginAPI {
  //   assert(
  //     function_exists('babylGetServices'),
  //     AssertionError('global fn babylGetServices() does not exist'));

  //   return babylGetServices()[IBabylonPlugin::SERVICE_KEY_PLUGIN_API];
  // }
}
