<?php
namespace Babylcraft\WordPress\Plugin;

use Babylcraft\BabylonException;

/**
 * Final cos don't want error code collisions in subclasses!
 */
final class BabylonPluginException extends BabylonException
{
    const ERR_PDO_EXCEPTION = 0x1;

    protected function codeToMessage(int $code, $context): string
    {
        $message = '';
        switch ($code) {
            case $this::ERR_PDO_EXCEPTION:
                $message = "PDO failure in $context. Exception not wrapped for security.";
                break;
            default:
                $message = "matching unknown error $code";
                break;
        }

        return $message;
    }
}