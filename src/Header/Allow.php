<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_keys;
use function explode;
use function implode;
use Laminas\Http\Request;
use function preg_match;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;
/**
 * Allow Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.7
 */
class Allow implements Header_Interface
{
    /**
     * List of request methods
     * true states that method is allowed, false - disallowed
     * By default GET and POST are allowed
     *
     * @var array
     */
    protected $methods = [Request::METHOD_OPTIONS => false, Request::METHOD_GET => true, Request::METHOD_HEAD => false, Request::METHOD_POST => true, Request::METHOD_PUT => false, Request::METHOD_DELETE => false, Request::METHOD_TRACE => false, Request::METHOD_CONNECT => false, Request::METHOD_PATCH => false];
    /**
     * Create Allow header from header line
     *
     * @param string $headerLine
     * @throws Exception\InvalidArgumentException
     */
    public static function from_string($header_line): static
    {
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'allow') {
            throw new Exception\InvalidArgumentException('Invalid header line for Allow string: "' . $name . '"');
        }
        $header = new static();
        $header->disallow_methods(array_keys($header->get_all_methods()));
        $header->allow_methods(explode(',', $value));
        return $header;
    }
    /**
     * Get header name
     */
    public function get_field_name(): string
    {
        return 'Allow';
    }
    /**
     * Get comma-separated list of allowed methods
     */
    public function get_field_value(): string
    {
        return implode(', ', array_keys($this->methods, true, true));
    }
    /**
     * Get list of all defined methods
     *
     * @return array
     */
    public function get_all_methods()
    {
        return $this->methods;
    }
    /**
     * Get list of allowed methods
     */
    public function get_allowed_methods(): array
    {
        return array_keys($this->methods, true, true);
    }
    /**
     * Allow methods or list of methods
     *
     * @param array|string $allowedMethods
     * @return $this
     */
    public function allow_methods($allowed_methods): static
    {
        foreach ((array) $allowed_methods as $method) {
            $method = trim(strtoupper((string) $method));
            if (preg_match('/\s/', $method)) {
                throw new Exception\InvalidArgumentException(sprintf('Unable to whitelist method; "%s" is not a valid method', $method));
            }
            $this->methods[$method] = true;
        }
        return $this;
    }
    /**
     * Disallow methods or list of methods
     *
     * @param array|string $disallowedMethods
     * @return $this
     */
    public function disallow_methods($disallowed_methods): static
    {
        foreach ((array) $disallowed_methods as $method) {
            $method = trim(strtoupper((string) $method));
            if (preg_match('/\s/', $method)) {
                throw new Exception\InvalidArgumentException(sprintf('Unable to blacklist method; "%s" is not a valid method', $method));
            }
            $this->methods[$method] = false;
        }
        return $this;
    }
    /**
     * Convenience alias for @see disallowMethods()
     *
     * @param array|string $disallowedMethods
     * @return $this
     */
    public function deny_methods($disallowed_methods)
    {
        return $this->disallow_methods($disallowed_methods);
    }
    /**
     * Check whether method is allowed
     *
     * @param string $method
     * @return bool
     */
    public function is_allowed_method($method)
    {
        $method = trim(strtoupper($method));
        // disallow unknown method
        if (!isset($this->methods[$method])) {
            $this->methods[$method] = false;
        }
        return $this->methods[$method];
    }
    /**
     * Return header as string
     */
    public function to_string(): string
    {
        return 'Allow: ' . $this->get_field_value();
    }
}