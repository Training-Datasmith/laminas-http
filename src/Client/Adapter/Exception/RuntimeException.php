<?php

declare(strict_types=1);

namespace Laminas\Http\Client\Adapter\Exception;

use Laminas\Http\Client\Exception;

class RuntimeException extends Exception\RuntimeException implements
    ExceptionInterface
{
}
