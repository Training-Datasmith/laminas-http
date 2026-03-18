<?php

declare(strict_types=1);

namespace Laminas\Http\Header\Exception;

use Laminas\Http\Exception;

class InvalidArgumentException extends Exception\InvalidArgumentException implements
    ExceptionInterface
{
}
