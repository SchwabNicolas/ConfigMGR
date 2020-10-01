<?php
namespace ConfigMGR\Exceptions;

use Throwable;

class JsonNotValidException extends \Exception {
    public function __construct($message = "The provided JSON file is not valid", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return $this->getMessage();
    }
}