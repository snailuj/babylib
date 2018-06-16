<?php
namespace Babylcraft\WordPress\MVC;

use Babylcraft\BabylonException;

class ControllerContainerException extends BabylonException
{
    const ERROR_NO_SUCH_CLASS = 0;
    const ERROR_NOT_A_CONTROLLER = 1;

    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        switch ($code) {
            case $this::ERROR_NO_SUCH_CLASS:
                $message = "Class $context not found";
                break;
            case $this::ERROR_NOT_A_CONTROLLER:
                $message = "Class $context was not of type IPluginController";
                break;
            default:
                $message = "matching unknown error $code";
                break;
        }

        return $message;
    }
}
