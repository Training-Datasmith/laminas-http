<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

/**
 * Location Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.30
 */
class Location extends Abstract_Location
{
    /**
     * Return header name
     */
    public function get_field_name(): string
    {
        return 'Location';
    }
}