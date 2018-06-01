<?php
namespace Babylcraft\WordPress\MVC;

use Babylcraft\BabylonException;

class ControllerContainerException extends BabylonException
{
    public const ERROR_NO_SUCH_CLASS = 0;

    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        switch ($code) {
            case $this::ERROR_NO_SUCH_CLASS:
                $message = "Class $context not found";
                break;
            default:
                $message = "matching unknown error $code";
                break;
        }

        return $message;
    }
}
