<?php
namespace Babylcraft\WordPress;

//refactor me: core classes should not depend on specific plugins
use Babylcraft\Plugin\IBabylonPlugin;

use DownShift\WordPress\EventEmitter;
use DownShift\Wordpress\EventEmitterInterface;

/**
 * Trait PluginAPI
 *
 * Handles interactions with the WordPress Plugin API
 *
 * @package Babylcraft\WordPress
 */
trait PluginAPI
{
    private static $eventEmitter;
    private static function getEventEmitter() : EventEmitterInterface
    {
        if (!self::$eventEmitter) {
            self::$eventEmitter = new EventEmitter();
        }

        return self::$eventEmitter;
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
        self::getEventEmitter()->on(
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
        self::getEventEmitter()->filter(
            $hookName,
            $filterFn,
            $priority,
            $acceptedArgs
        );
    }

    public function getOption(string $optionName) {
        return get_option($optionName);
    }

    public function isDebug() : bool {
        return defined('WP_DEBUG');
    }

    public function isAdminDashboard() : bool
    {
        if (function_exists('get_current_screen')) {
            $adminPage = get_current_screen();
        }

        return $adminPage->base == 'dashboard';
    }

    //TODO test if this works when plugin ISN'T symlinked?
    public function registerSymlinkPlugin(string $pluginDir) : bool {
        return wp_register_plugin_realpath($pluginDir);
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
        $path .= $useParent ? "" : '/placeholdertext';
        PluginAPI::logMessage("using ". $path .", returning ". plugin_dir_url($path));
        return plugin_dir_url($path);
    }

    public function trailingslashit(string $pathOrURI) : string
    {
        return trailingslashit($pathOrURI);
    }

    public function createNonce(string $handle) : string
    {
        return wp_create_nonce($handle);
    }

    public function localizeScript(string $handle, string $settingsName = 'settings', array $settings = [])
    {
        return wp_localize_script($handle, $settingsName, $settings);
    }

    public static function registerActivationHook(string $file, $hookFn) {
        register_activation_hook($file, $hookFn);
    }

    public static function registerDeactivationHook(string $file, $hookFn) {
        register_deactivation_hook($file, $hookFn);
    }

    public static function isBabylonPluginActive(string $pluginName) : bool {
        $pluginLocation = "{$pluginName}/{$pluginName}.php";
        return in_array($pluginLocation, get_option('active_plugins', []));
    }

    public static function logContent(string $message, $content, $fileName = '', $lineNum = '')
    {
        if (is_array($content) || is_object($content)) {
            PluginAPI::logMessage("{$message}: ". print_r($content, true), $fileName, $lineNum);
        } else {
            PluginAPI::logMessage("{$message}: {$content}", $fileName, $lineNum);
        }
    }

    public static function logMessage(string $message, $fileName = '', $lineNum = '')
    {
        error_log(
            $message
            .($fileName ? "\nat $fileName" : '') . ($lineNum ? ": $lineNum" : '') ."\n"
        );
    }
}
