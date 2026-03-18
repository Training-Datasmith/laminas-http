<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

/**
 * Content-Location Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.14
 */
class ContentLocation extends AbstractLocation
{
    /**
     * Return header name
     */
    public function getFieldName(): string
    {
        return 'Content-Location';
    }
}
