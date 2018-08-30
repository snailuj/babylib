<?php

namespace Babylcraft\WordPress\MVC\Controller;

use Babylcraft\BabylonException;

class PluginControllerException extends BabylonException
{
    const ERR_NOT_CONFIGURED = 0x1;
    const ERR_BAD_MODEL_FACTORY = 0x2;

    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        $context = (string)$context;
        if ($this->codeIncludesError($this::ERR_NOT_CONFIGURED)) {
            $message .= "Attempt to set model factory for Controller ($context), which is not configured. Call $context->configure() first.";
        }

        if ($this->codeIncludesError($this::ERR_BAD_MODEL_FACTORY)) {
            $message .= "Incorrect ModelFactory subtype in {$context}. ";
        }

        return $message;
    }
}