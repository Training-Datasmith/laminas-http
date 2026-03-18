<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

/**
 * Last-Modified Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.29
 */
class LastModified extends AbstractDate
{
    /**
     * Get header name
     */
    public function getFieldName(): string
    {
        return 'Last-Modified';
    }
}
