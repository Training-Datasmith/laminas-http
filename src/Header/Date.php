<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

/**
 * Date Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.18
 */
class Date extends Abstract_Date
{
    /**
     * Get header name
     */
    public function get_field_name(): string
    {
        return 'Date';
    }
}