<?php

declare(strict_types=1);

namespace Laminas\Http\Client\Adapter\Exception;

class TimeoutException extends RuntimeException implements ExceptionInterface
{
    public const READ_TIMEOUT = 1000;
}
