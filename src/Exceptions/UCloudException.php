<?php

namespace UCloud\Storage\Exceptions;

use Exception;

class UCloudException extends Exception
{
    protected $errRet;

    public function __construct(string $message = "", int $code = 0, $errRet = 0)
    {
        $this->errRet = $errRet;
        parent::__construct($message, $code);
    }

    public function getErrRet()
    {
        return $this->errRet;
    }
}
