<?php

namespace Laminas\Http\Header;

/**
 * If-Modified-Since Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.25
 */
class IfModifiedSince extends AbstractDate
{
    /**
     * Get header name
     */
    public function getFieldName(): string
    {
        return 'If-Modified-Since';
    }
}
