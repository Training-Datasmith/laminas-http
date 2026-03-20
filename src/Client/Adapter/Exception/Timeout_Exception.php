<?php

declare (strict_types=1);
namespace Laminas\Http\Client\Adapter\Exception;

class Timeout_Exception extends RuntimeException implements Exception_Interface
{
    public const READ_TIMEOUT = 1000;
}