<?php

declare(strict_types=1);

namespace Laminas\Http\Client\Exception;

use Laminas\Http\Exception;

class OutOfRangeException extends Exception\OutOfRangeException implements
    ExceptionInterface
{
}
