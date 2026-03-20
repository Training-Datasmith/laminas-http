<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function strtolower;
use function trim;
/**
 * Connection Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.10
 */
class Connection implements Header_Interface
{
    public const CONNECTION_CLOSE = 'close';
    public const CONNECTION_KEEP_ALIVE = 'keep-alive';
    /**
     * Value of this header
     *
     * @var string
     */
    protected $value = self::CONNECTION_KEEP_ALIVE;
    /**
     * @param string $headerLine
     * @throws Exception\InvalidArgumentException
     */
    public static function from_string($header_line): static
    {
        $header = new static();
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'connection') {
            throw new Exception\InvalidArgumentException('Invalid header line for Connection string: "' . $name . '"');
        }
        $header->set_value(trim($value));
        return $header;
    }
    /**
     * Set Connection header to define persistent connection
     *
     * @param  bool $flag
     * @return $this
     */
    public function set_persistent($flag): static
    {
        $this->value = (bool) $flag ? self::CONNECTION_KEEP_ALIVE : self::CONNECTION_CLOSE;
        return $this;
    }
    /**
     * Get whether this connection is persistent
     */
    public function is_persistent(): bool
    {
        return $this->value === self::CONNECTION_KEEP_ALIVE;
    }
    /**
     * Set arbitrary header value
     * RFC allows any token as value, 'close' and 'keep-alive' are commonly used
     *
     * @param string $value
     * @return $this
     */
    public function set_value($value): static
    {
        Header_Value::assert_valid($value);
        $this->value = strtolower($value);
        return $this;
    }
    /**
     * Connection header name
     */
    public function get_field_name(): string
    {
        return 'Connection';
    }
    /**
     * Connection header value
     *
     * @return string
     */
    public function get_field_value()
    {
        return $this->value;
    }
    /**
     * Return header line
     */
    public function to_string(): string
    {
        return 'Connection: ' . $this->get_field_value();
    }
}