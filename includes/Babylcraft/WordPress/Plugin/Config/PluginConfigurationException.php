<?php
namespace Babylcraft\WordPress\Plugin\Config;

use Babylcraft\BabylonException;

class PluginConfigurationException extends BabylonException
{
    public const ERROR_CONTROLLER_DIR_NOT_FOUND = 0x1;
    public const ERROR_PLUGIN_ALREADY_ACTIVE = 0x2;
    public const ERROR_FACTORY_CLASS_NOT_FOUND = 0x4;
    public const ERROR_GENERAL_ERROR = 0x8;
    public const ERROR_NOT_A_MODEL_FACTORY = 0x10;

    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        $context = (string)$context;
        if ($this->codeIncludesError($this::ERROR_CONTROLLER_DIR_NOT_FOUND)) {
            $message .= "Controller directory $context not found. ";
        }

        if ($this->codeIncludesError($this::ERROR_PLUGIN_ALREADY_ACTIVE)) {
            $message .= "::activate() called but plugin is already activated. ";
        }

        if ($this->codeIncludesError($this::ERROR_FACTORY_CLASS_NOT_FOUND)) {
            $message .= "Factory class $context not found. ";
        }

        if ($this->codeIncludesError($this::ERROR_NOT_A_MODEL_FACTORY)) {
            $message .= "Object $context is not an instance of IModelFactory. ";
        }

        if ($this->codeIncludesError($this::ERROR_GENERAL_ERROR)) {
            $message .= "General error. ";
        }

        return $message;
    }
}
