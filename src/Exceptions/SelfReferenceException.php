<?php
namespace ConfigMGR\Exceptions;

use Throwable;

class SelfReferenceException extends \Exception
{
    public function __construct($message = "The key either references itself or a child references its parent", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return $this->getMessage();
    }
}