<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\BabylonException;

/**
 * Final cos don't want error code collisions in subclasses!
 */
final class BabylonPluginException extends BabylonException
{
    const ERR_PDO_EXCEPTION = 0x1;
    const ERR_ACTIVATION_FAILED = 0x2;
    const ERR_OTHER = 0x4;

    protected function codeToMessage(int $code, $context): string
    {
        $message = '';
        $pluginName = $context ? $context->getPluginName() : 'plugin';
        if ($this->codeIncludesError($this::ERR_PDO_EXCEPTION)) {
            $message .= "PDO failure in {$pluginName}. Exception not wrapped for security. ";
        }

        if ($this->codeIncludesError($this::ERR_ACTIVATION_FAILED)) {
            $message .= "Activation of $pluginName failed. ";
        }

        if ($this->codeIncludesError($this::ERR_OTHER)) {
            $message .= "General error. ";
        }
        
        return $message;
    }
}