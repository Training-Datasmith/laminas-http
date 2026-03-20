<?php

declare (strict_types=1);
namespace Laminas\Http\Client\Adapter\Exception;

use Laminas\Http\Client\Exception;
class InvalidArgumentException extends Exception\InvalidArgumentException implements Exception_Interface
{
}