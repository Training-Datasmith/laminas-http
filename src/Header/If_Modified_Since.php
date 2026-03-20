<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

/**
 * If-Modified-Since Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.25
 */
class If_Modified_Since extends Abstract_Date
{
    /**
     * Get header name
     */
    public function get_field_name(): string
    {
        return 'If-Modified-Since';
    }
}