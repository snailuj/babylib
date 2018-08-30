<?php
namespace Babylcraft\WordPress;

//refactor me: core classes should not depend on specific plugins
use Babylcraft\Plugin\IBabylonPlugin;

use DownShift\WordPress\EventEmitter;
use DownShift\Wordpress\EventEmitterInterface;
use Babylcraft\Babylon;

/**
 * Trait PluginAPI
 *
 * Handles interactions with the WordPress Plugin API
 *
 * @package Babylcraft\WordPress
 */
trait PluginAPI
{
    use DBAPI;

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

    public static function debug(string $message, $fileName = '', $lineNum = '')
    {
        static::logMessage($message, Babylon::LOG_DEBUG, $fileName, $lineNum);
    }

    public static function info(string $message, $fileName = '', $lineNum = '')
    {
        static::logMessage($message, Babylon::LOG_INFO, $fileName, $lineNum);
    }

    public static function warn(string $message, $fileName = '', $lineNum = '')
    {
        static::logMessage($message, Babylon::LOG_WARN, $fileName, $lineNum);
    }

    public static function error(string $message, $fileName = '', $lineNum = '')
    {
        static::logMessage($message, Babylon::LOG_ERROR, $fileName, $lineNum);
    }

    public static function debugContent($content, string $message = '', $fileName = '', $lineNum = '')
    {
        static::logContent($message, Babylon::LOG_DEBUG, $content, $fileName, $lineNum);
    }

    public static function infoContent($content, string $message = '', $fileName = '', $lineNum = '')
    {
        static::logContent($message, Babylon::LOG_INFO, $content, $fileName, $lineNum);
    }

    public static function warnContent($content, string $message = '', $fileName = '', $lineNum = '')
    {
        static::logContent($message, Babylon::LOG_WARN, $content, $fileName, $lineNum);
    }

    public static function errorContent($content, string $message = '', $fileName = '', $lineNum = '')
    {
        static::logContent($message, Babylon::LOG_ERROR, $content, $fileName, $lineNum);
    }

    public static function logContent(string $message, int $logLevel, $content, $fileName = '', $lineNum = '')
    {
        if (is_array($content) || is_object($content)) {
            static::logMessage("{$message}: ". print_r($content, true), $logLevel, $fileName, $lineNum);
        } else {
            static::logMessage("{$message}: {$content}", $logLevel, $fileName, $lineNum);
        }
    }

    public static function logMessage(string $message, int $logLevel, $fileName = '', $lineNum = '')
    {
        if (function_exists("babylGetServices") && $logLevel < babylGetServices()[Babylon::KEY_LOG_LEVEL]) {
            return;
        }

        //if no config found then just log away happily
        error_log(
            $message
            .($fileName ? "\nat $fileName" : '') . ($lineNum ? ": $lineNum" : '') ."\n"
        );
    }
}
