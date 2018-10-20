<?php

namespace Babylcraft\WordPress\MVC\Model;

use Babylcraft\BabylonException;

class FieldException extends BabylonException
{
    const ERR_NOT_FOUND        = 0x1;
    const ERR_UNIQUE_VIOLATION = 0x2;
    const ERR_READ_ONLY        = 0x4;
    const ERR_IS_NULL          = 0x8;
    const ERR_WRONG_TYPE       = 0x10;
    const ERR_ALREADY_DEFINED  = 0x20;

    const ALL_VALIDATABLE  
        = self::ERR_NOT_FOUND
        | self::ERR_UNIQUE_VIOLATION //TODO? needs validation from the Model's parent, requires generic "children" field
        | self::ERR_READ_ONLY
        | self::ERR_IS_NULL
        | self::ERR_WRONG_TYPE;
    
    const NONE = 0;
    
    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';        
        if ($this->codeIncludesError($this::ERR_NOT_FOUND)) {
            $message .= "Field not found.";
        }

        if ($this->codeIncludesError($this::ERR_UNIQUE_VIOLATION)) {
            $message .= "Uniqueness constraint violation.";
        }

        if ($this->codeIncludesError($this::ERR_READ_ONLY)) {
            $message .= "Field is readonly.";
        }

        if ($this->codeIncludesError($this::ERR_IS_NULL)) {
            $message .= "Field is null, but required.";
        }

        if ($this->codeIncludesError($this::ERR_WRONG_TYPE)) {
            $message .= "Wrong type.";
        }

        if ($this->codeIncludesError($this::ERR_ALREADY_DEFINED)) {
            $message .= "Field already defined.";
        }
        
        return $message .($context ? " ". (string)$context ." " : "");
    }
}