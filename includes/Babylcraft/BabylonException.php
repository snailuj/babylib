<?php
namespace Babylcraft;

/*
 * Strict exceptions that take a code and require
 * implementing a translation method from code to message
 */
abstract class BabylonException extends \Exception
{
    /**
     * @var mixed
     */
    var $context;

    public function __construct($code, $context = null, $previous = null)
    {
        $this->code = $code;
        parent::__construct($this->codeToMessage($code, $context), $code, $previous);
        $this->context = $context;
    }

    abstract protected function codeToMessage(int $code, $context) : string;

    /**
     * Best gone done make sure you only pass powers of 2 to this function
     */
    public function addErrorCode($errorCode) {
        $this->code = $this->code | $errorCode;
        $this->message = $this->codeToMessage($this->code, $this->context);
    }

    // custom string representation of object
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    protected function codeIncludesError(int $error) : bool {
        return ($this->code & $error) != 0; //assumes error codes are powers of 2
    }
}
