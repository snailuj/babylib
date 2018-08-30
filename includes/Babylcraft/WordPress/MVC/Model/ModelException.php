<?php
namespace Babylcraft\WordPress\MVC\Model;

use Babylcraft\BabylonException;


class ModelException extends BabylonException
{
    const ERR_PDO_EXCEPTION = 0x1;
    const ERR_RECORD_NOT_FOUND = 0x2;
    const ERR_OTHER = 0x4;
    const ERR_SCHEMA_EXISTS = 0x8;
    const ERR_SCHEMA_NOT_EXISTS = 0x10;
    const ERR_OPTION_UPDATE_FAILED = 0x20;
    const ERR_FIELD_NOT_FOUND = 0x40;
    const ERR_BAD_MODEL_FACTORY = 0x80;

    protected function codeToMessage(int $code, $context) : string
    {
        $message = '';
        $context = (string)$context;
        if ($this->codeIncludesError($this::ERR_PDO_EXCEPTION)) {
            $message .= "PDO failure in {$context}. Exception not wrapped for security. ";
        }

        if ($this->codeIncludesError($this::ERR_RECORD_NOT_FOUND)) {
            $message .= "Object {$context} not found. ";
        }

        if ($this->codeIncludesError($this::ERR_SCHEMA_EXISTS)) {
            $message .= "Schema already exists in {$context}. Refusing to create. ";
        }

        if ($this->codeIncludesError($this::ERR_SCHEMA_NOT_EXISTS)) {
            $message .= "Schema does not exist in {$context}. Refusing to delete. ";
        }

        if ($this->codeIncludesError($this::ERR_OPTION_UPDATE_FAILED)) {
            $message .= "Failed to update option {$context}.";
        }

        if ($this->codeIncludesError($this::ERR_FIELD_NOT_FOUND)) {
            $message .= "Field {$context} not found. ";
        }

        if ($this->codeIncludesError($this::ERR_BAD_MODEL_FACTORY)) {
            $message .= "Incorrect ModelFactory subtype in {$context}. ";
        }

        if ($this->codeIncludesError($this::ERR_OTHER)) {
            $message .= "General error. ";
        }
        
        return $message;
    }
}
