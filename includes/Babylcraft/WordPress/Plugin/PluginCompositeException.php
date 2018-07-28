<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\BabylonException;

class PluginCompositeException extends BabylonException
{
    const ERROR_NO_SUCH_CLASS = 0x1;
    const ERROR_NOT_A_PLUGIN = 0x2;

    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        switch ($code) {
            case $this::ERROR_NO_SUCH_CLASS:
                $message = "Class $context not found";
                break;
            case $this::ERROR_NOT_A_PLUGIN:
                $message = "Class $context was not of type IBabylonPlugin";
                break;
            default:
                $message = "matching unknown error $code";
                break;
        }

        return $message;
    }
}
