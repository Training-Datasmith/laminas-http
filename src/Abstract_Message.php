<?php

declare (strict_types=1);
namespace Laminas\Http;

use function in_array;
use function is_string;
use Laminas\Stdlib\Message;
/**
 * HTTP standard message (Request/Response)
 *
 * @link      http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4
 */
abstract class Abstract_Message extends Message implements \Stringable
{
    /**#@+
     *
     * @const string Version constant numbers
     */
    public const VERSION_10 = '1.0';
    public const VERSION_11 = '1.1';
    public const VERSION_2 = '2';
    /**#@-*/
    /** @var string */
    protected $version = self::VERSION_11;
    /** @var Headers|null */
    protected $headers;
    /**
     * Set the HTTP version for this message.
     *
     * Accepted values are the class constants VERSION_10 ('1.0'), VERSION_11 ('1.1'),
     * and VERSION_2 ('2'). Using an unsupported version string will throw immediately.
     *
     * @param string $version One of the VERSION_* class constants
     * @return static Fluent interface
     * @throws Exception\InvalidArgumentException If $version is not a recognised HTTP version
     * @since 2.0.0
     */
    public function set_version(string $version): static
    {
        if (!in_array($version, [self::VERSION_10, self::VERSION_11, self::VERSION_2])) {
            throw new Exception\InvalidArgumentException('Not valid or not supported HTTP version: ' . $version);
        }
        $this->version = $version;
        return $this;
    }
    /**
     * Return the HTTP version for this message.
     *
     * @return string One of the VERSION_* constants ('1.0', '1.1', or '2')
     * @since 2.0.0
     */
    public function get_version(): string
    {
        return $this->version;
    }
    /**
     * Replace the headers container for this message.
     *
     * This is NOT the primary API for adding individual headers; use get_headers()
     * and operate on the returned Headers container instead.
     *
     * @param Headers $headers A fully-constructed headers container to attach
     * @return static Fluent interface
     * @see get_headers() For adding or reading individual headers
     * @since 2.0.0
     */
    public function set_headers(Headers $headers): static
    {
        $this->headers = $headers;
        return $this;
    }
    /**
     * Return the headers container for this message, lazy-initialising if needed.
     *
     * If no container has been set, a new empty Headers instance is created.
     * If a raw string was assigned (e.g. during fromString lazy loading), it is
     * parsed into a Headers object on first access.
     *
     * @return Headers The headers container for this message
     * @since 2.0.0
     */
    public function get_headers(): Headers
    {
        if ($this->headers === null || is_string($this->headers)) {
            // this is only here for fromString lazy loading
            $this->headers = is_string($this->headers) ? Headers::from_string($this->headers) : new Headers();
        }
        return $this->headers;
    }
    /**
     * Allow PHP casting of this object
     */
    public function __toString(): string
    {
        return (string) $this->to_string();
    }
}