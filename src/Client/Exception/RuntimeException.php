<?php

declare(strict_types=1);

namespace Laminas\Http\Client\Exception;

use Laminas\Http\Exception;

class RuntimeException extends Exception\RuntimeException implements
    ExceptionInterface
{
}
