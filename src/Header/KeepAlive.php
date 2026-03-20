<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function strtolower;
/**
 * @throws Exception\InvalidArgumentException
 * @todo Search for RFC for this header
 */
class Keep_Alive implements Header_Interface
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
        if (strtolower($name) !== 'keep-alive') {
            throw new Exception\InvalidArgumentException('Invalid header line for Keep-Alive string: "' . $name . '"');
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
        return 'Keep-Alive';
    }
    public function get_field_value(): string
    {
        return (string) $this->value;
    }
    public function to_string(): string
    {
        return 'Keep-Alive: ' . $this->get_field_value();
    }
}