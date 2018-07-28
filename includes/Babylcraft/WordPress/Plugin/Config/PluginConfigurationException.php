<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\BabylonException;

class PluginConfigurationException extends BabylonException
{
    public const ERROR_CONTROLLER_DIR_NOT_FOUND = 0x1;
    public const ERROR_PLUGIN_ALREADY_ACTIVE = 0x2;

    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        switch ($code) {
            case $this::ERROR_CONTROLLER_DIR_NOT_FOUND:
                $message = "Controller directory $context not found";
                break;
            case $this::ERROR_PLUGIN_ALREADY_ACTIVE:
                $message = "$context::activate() called but plugin is already activated";
                break;
            default:
                "matching unknown error code $code";
                break;
        }

        return $message;
    }
}
