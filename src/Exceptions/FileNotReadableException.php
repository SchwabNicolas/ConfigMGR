<?php
namespace ConfigMGR\Exceptions;

use Throwable;

class FileNotReadableException extends \Exception
{
    public function __construct($message = "Provided file is not found or not readable", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return $this->getMessage();
    }
}