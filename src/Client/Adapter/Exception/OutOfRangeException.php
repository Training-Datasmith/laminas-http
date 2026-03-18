<?php

declare(strict_types=1);

namespace Laminas\Http\Client\Adapter\Exception;

use Laminas\Http\Client\Exception;

class OutOfRangeException extends Exception\OutOfRangeException implements
    ExceptionInterface
{
}
