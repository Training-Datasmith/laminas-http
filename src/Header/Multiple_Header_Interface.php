<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

interface Multiple_Header_Interface extends Header_Interface
{
    /**
     * Convert multiple headers to string representation
     *
     * @param array $headers Array of header instances
     * @return string
     */
    public function to_string_multiple_headers(array $headers);
}