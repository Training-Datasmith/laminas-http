<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function is_string;
use Laminas\Uri\Exception as UriException;
use Laminas\Uri\Uri_Factory;
use Laminas\Uri\Uri_Interface;
use function sprintf;
use function strtolower;
use function trim;
/**
 * Abstract Location Header
 * Supports headers that have URI as value
 *
 * @see Laminas\Http\Header\Location
 * @see Laminas\Http\Header\ContentLocation
 * @see Laminas\Http\Header\Referer
 *
 * Note for 'Location' header:
 * While RFC 1945 requires an absolute URI, most of the browsers also support relative URI
 * This class allows relative URIs, and let user retrieve URI instance if strict validation needed
 */
abstract class Abstract_Location implements Header_Interface, \Stringable
{
    /**
     * URI for this header
     *
     * @var UriInterface
     */
    protected $uri;
    /**
     * Create location-based header from string
     *
     * @param string $headerLine
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function from_string($header_line)
    {
        $location_header = new static();
        // Laminas-5520 - IIS bug, no space after colon
        [$name, $uri] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== strtolower($location_header->get_field_name())) {
            throw new Exception\InvalidArgumentException('Invalid header line for "' . $location_header->get_field_name() . '" header string');
        }
        Header_Value::assert_valid($uri);
        $location_header->set_uri(trim($uri));
        return $location_header;
    }
    /**
     * Set the URI/URL for this header, this can be a string or an instance of Laminas\Uri\Http
     *
     * @param string|UriInterface $uri
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function set_uri($uri)
    {
        if (is_string($uri)) {
            try {
                $uri = Uri_Factory::factory($uri);
            } catch (Uri_Exception\Invalid_Uri_Part_Exception|Uri_Exception\InvalidArgumentException $e) {
                throw new Exception\InvalidArgumentException(sprintf('Invalid URI passed as string (%s)', $uri), $e->get_code(), $e);
            }
        } elseif (!$uri instanceof Uri_Interface) {
            throw new Exception\InvalidArgumentException('URI must be an instance of Laminas\Uri\Http or a string');
        }
        $this->uri = $uri;
        return $this;
    }
    /**
     * Return the URI for this header
     *
     * @return string
     */
    public function get_uri()
    {
        if ($this->uri instanceof Uri_Interface) {
            return $this->uri->to_string();
        }
        return $this->uri;
    }
    /**
     * Return the URI for this header as an instance of Laminas\Uri\Http
     *
     * @return UriInterface
     */
    public function uri()
    {
        if ($this->uri === null || is_string($this->uri)) {
            $this->uri = Uri_Factory::factory($this->uri);
        }
        return $this->uri;
    }
    /**
     * Get header value as URI string
     *
     * @return string
     */
    public function get_field_value()
    {
        return $this->get_uri();
    }
    /**
     * Output header line
     *
     * @return string
     */
    public function to_string()
    {
        return $this->get_field_name() . ': ' . $this->get_uri();
    }
    /**
     * Allow casting to string
     */
    public function __toString(): string
    {
        return $this->to_string();
    }
}