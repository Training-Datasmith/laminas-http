<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

/**
 * Location Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.30
 */
class Location extends AbstractLocation
{
    /**
     * Return header name
     */
    public function getFieldName(): string
    {
        return 'Location';
    }
}
