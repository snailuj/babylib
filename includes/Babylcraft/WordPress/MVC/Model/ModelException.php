<?php
namespace Babylcraft\WordPress\MVC\Model;

use Babylcraft\BabylonException;


class ModelException extends BabylonException
{   
    const ERR_PDO_EXCEPTION        = 0x1;
    const ERR_RECORD_NOT_FOUND     = 0x2;
    const ERR_OPTION_UPDATE_FAILED = 0x4;
    const ERR_BAD_MODEL_FACTORY    = 0x8;
    const ERR_OTHER                = 0x10;
    const ERR_UNKNOWN_MAPPING      = 0x20;
    const ERR_NO_ID                = 0x40;
    const ERR_UNIQUE_VIOLATION     = 0x80;
    
    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        $context = (string)$context;
        if ($this->codeIncludesError($this::ERR_PDO_EXCEPTION)) {
            $message .= "PDO failure. Exception not wrapped for security.";
        }

        if ($this->codeIncludesError($this::ERR_RECORD_NOT_FOUND)) {
            $message .= "Object not found.";
        }

        if ($this->codeIncludesError($this::ERR_OPTION_UPDATE_FAILED)) {
            $message .= "Failed to update option.";
        }

        if ($this->codeIncludesError($this::ERR_BAD_MODEL_FACTORY)) {
            $message .= "Incorrect ModelFactory subtype.";
        }

        if ($this->codeIncludesError($this::ERR_UNKNOWN_MAPPING)) {
            $message .= "Unknown mapping.";
        }

        if ($this->codeIncludesError($this::ERR_OTHER)) {
            $message .= "General error.";
        }

        if ($this->codeIncludesError($this::ERR_NO_ID)) {
            $message .= "Model has no value set for F_ID.";
        }

        if ($this->codeIncludesError($this::ERR_UNIQUE_VIOLATION)) {
            $message .= "Uniqueness constraint violation.";
        }
        
        return $message .($context ? " ". (string)$context ." " : "");
    }
}
