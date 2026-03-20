<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function strtolower;
/**
 * @throws Exception\InvalidArgumentException
 * @todo FIND SPEC FOR THIS
 */
class Refresh implements Header_Interface
{
    /** @var string */
    protected $value;
    /**
     * @param string $headerLine
     */
    public static function from_string($header_line): static
    {
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'refresh') {
            throw new Exception\InvalidArgumentException('Invalid header line for Refresh string: "' . $name . '"');
        }
        // @todo implementation details
        return new static($value);
    }
    /** @param null|string $value */
    public function __construct($value = null)
    {
        if ($value !== null) {
            Header_Value::assert_valid($value);
            $this->value = $value;
        }
    }
    public function get_field_name(): string
    {
        return 'Refresh';
    }
    public function get_field_value(): string
    {
        return (string) $this->value;
    }
    public function to_string(): string
    {
        return 'Refresh: ' . $this->get_field_value();
    }
}