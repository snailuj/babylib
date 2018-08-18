<?php
namespace Babylcraft;

/*
 * Strict exceptions that take a code and require
 * implementing a translation method from code to message
 */
abstract class BabylonException extends \Exception
{    
    public function __construct($code, $context = null, $previous = null)
    {
        parent::__construct($this->codeToMessage($code, $context), $code, $previous);
    }

    abstract protected function codeToMessage(int $code, $context) : string;

  // custom string representation of object
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
