<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function sprintf;
use function strtolower;
/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.26
 *
 * @throws Exception\InvalidArgumentException
 */
class If_None_Match implements Header_Interface
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
        if (strtolower($name) !== 'if-none-match') {
            throw new Exception\InvalidArgumentException(sprintf('Invalid header line for If-None-Match string: "%s"', $name));
        }
        // @todo implementation details
        return new static($value);
    }
    /** @param string $value */
    public function __construct($value = null)
    {
        if ($value !== null) {
            Header_Value::assert_valid($value);
            $this->value = $value;
        }
    }
    public function get_field_name(): string
    {
        return 'If-None-Match';
    }
    public function get_field_value(): string
    {
        return (string) $this->value;
    }
    public function to_string(): string
    {
        return 'If-None-Match: ' . $this->get_field_value();
    }
}