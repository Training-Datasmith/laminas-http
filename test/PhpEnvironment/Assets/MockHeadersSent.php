<?php

declare(strict_types=1);

namespace Laminas\Http\PhpEnvironment;

// Define a custom headers_sent function within the namespace
function headers_sent(&$filename = null, &$line = null): bool
{
    return true;
}
