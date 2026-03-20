<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function is_int;
use function is_numeric;
use const PHP_INT_MAX;
use function strtolower;
/**
 * Age HTTP Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.6
 */
class Age implements Header_Interface
{
    /**
     * Estimate of the amount of time in seconds since the response
     *
     * @var int
     */
    protected $delta_seconds;
    /**
     * Create Age header from string
     *
     * @param string $headerLine
     * @throws Exception\InvalidArgumentException
     */
    public static function from_string($header_line): static
    {
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'age') {
            throw new Exception\InvalidArgumentException('Invalid header line for Age string: "' . $name . '"');
        }
        return new static($value);
    }
    /** @param null|int $deltaSeconds */
    public function __construct($delta_seconds = null)
    {
        if ($delta_seconds !== null) {
            $this->set_delta_seconds($delta_seconds);
        }
    }
    /**
     * Get header name
     */
    public function get_field_name(): string
    {
        return 'Age';
    }
    /**
     * Get header value (number of seconds)
     */
    public function get_field_value(): string
    {
        return (string) $this->get_delta_seconds();
    }
    /**
     * Set number of seconds
     *
     * @param int $delta
     * @return $this
     */
    public function set_delta_seconds($delta): static
    {
        if (!is_int($delta) && !is_numeric($delta)) {
            throw new Exception\InvalidArgumentException('Invalid delta provided');
        }
        $this->delta_seconds = (int) $delta;
        return $this;
    }
    /**
     * Get number of seconds
     *
     * @return int
     */
    public function get_delta_seconds()
    {
        return $this->delta_seconds;
    }
    /**
     * Return header line
     * In case of overflow RFC states to set value of 2147483648 (2^31)
     */
    public function to_string(): string
    {
        return 'Age: ' . ($this->delta_seconds >= PHP_INT_MAX ? '2147483648' : $this->delta_seconds);
    }
}