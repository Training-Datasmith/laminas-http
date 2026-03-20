<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

/**
 * Last-Modified Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.29
 */
class Last_Modified extends Abstract_Date
{
    /**
     * Get header name
     */
    public function get_field_name(): string
    {
        return 'Last-Modified';
    }
}