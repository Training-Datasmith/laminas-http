<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function strtolower;
/**
 * Accept Ranges Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.5
 */
class Accept_Ranges implements Header_Interface
{
    /** @var null|string */
    protected $range_unit;
    /**
     * @param string $headerLine
     */
    public static function from_string($header_line): static
    {
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'accept-ranges') {
            throw new Exception\InvalidArgumentException('Invalid header line for Accept-Ranges string');
        }
        return new static($value);
    }
    /** @param null|string $rangeUnit */
    public function __construct($range_unit = null)
    {
        if ($range_unit !== null) {
            $this->set_range_unit($range_unit);
        }
    }
    public function get_field_name(): string
    {
        return 'Accept-Ranges';
    }
    /** @return string */
    public function get_field_value()
    {
        return $this->get_range_unit();
    }
    /**
     * @param string $rangeUnit
     */
    public function set_range_unit($range_unit): static
    {
        Header_Value::assert_valid($range_unit);
        $this->range_unit = $range_unit;
        return $this;
    }
    public function get_range_unit(): string
    {
        return (string) $this->range_unit;
    }
    public function to_string(): string
    {
        return 'Accept-Ranges: ' . $this->get_field_value();
    }
}