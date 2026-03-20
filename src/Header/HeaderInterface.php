<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

/**
 * Interface for HTTP Header classes.
 */
interface Header_Interface
{
    /**
     * Factory to generate a header object from a string
     *
     * @see http://tools.ietf.org/html/rfc2616#section-4.2
     *
     * @param string $headerLine
     * @return static
     * @throws Exception\InvalidArgumentException If the header does not match RFC 2616 definition.
     */
    public static function from_string($header_line);
    /**
     * Retrieve header name
     *
     * @return string
     */
    public function get_field_name();
    /**
     * Retrieve header value
     *
     * @return string
     */
    public function get_field_value();
    /**
     * Cast to string
     *
     * Returns in form of "NAME: VALUE"
     *
     * @return string
     */
    public function to_string();
}