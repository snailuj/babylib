<?php
namespace Babylcraft\WordPress\MVC\Model;

class ModelException extends Babylcraft\BabylonException
{
    const ERROR_NOT_FOUND = 0;
    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        switch ($code) {
            case $this::ERROR_NOT_FOUND:
                $message = "object $context not found";
                break;
            default:
                $message = "matching unknown error $code";
                break;
        }

        return $message;
    }
}
?>