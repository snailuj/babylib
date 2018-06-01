<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\BabylonException;

class PluginConfigurationException extends BabylonException
{
    public const ERROR_CONTROLLER_DIR_NOT_FOUND = 0;

    protected function codeToMessage($code, $context)
    {
        $message = '';
        switch ($code) {
            case ERROR_CONTROLLER_DIR_NOT_FOUND:
                $message = "Controller directory $context not found";
                break;
            default:
                "matching unknown error code $code";
                break;
        }

        return $message;
    }
}
