<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function is_numeric;
use function strtolower;
/**
 * Retry-After HTTP Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.37
 */
class Retry_After extends Abstract_Date
{
    /**
     * Value of header in delta-seconds
     * By default set to 1 hour
     *
     * @var int
     */
    protected $delta_seconds = 3600;
    /**
     * Create Retry-After header from string
     *
     * @param  string $headerLine
     * @throws Exception\InvalidArgumentException
     */
    public static function from_string($header_line): static
    {
        $date_header = new static();
        [$name, $date] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== strtolower($date_header->get_field_name())) {
            throw new Exception\InvalidArgumentException('Invalid header line for "' . $date_header->get_field_name() . '" header string');
        }
        if (is_numeric($date)) {
            $date_header->set_delta_seconds($date);
        } else {
            $date_header->set_date($date);
        }
        return $date_header;
    }
    /**
     * Set number of seconds
     *
     * @param int $delta
     * @return $this
     */
    public function set_delta_seconds($delta): static
    {
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
     * Get header name
     */
    public function get_field_name(): string
    {
        return 'Retry-After';
    }
    /**
     * Returns date if it's set, or number of seconds
     *
     * @return int|string
     */
    public function get_field_value()
    {
        return $this->date === null ? $this->delta_seconds : $this->get_date();
    }
    /**
     * Return header line
     */
    public function to_string(): string
    {
        return 'Retry-After: ' . $this->get_field_value();
    }
}