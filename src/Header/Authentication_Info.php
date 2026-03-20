<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function sprintf;
use function strtolower;
/**
 * @see http://www.ietf.org/rfc/rfc2617.txt
 *
 * @throws Exception\InvalidArgumentException
 */
class Authentication_Info implements Header_Interface
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
        if (strtolower($name) !== 'authentication-info') {
            throw new Exception\InvalidArgumentException(sprintf('Invalid header line for Authentication-Info string: "%s"', $name));
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
        return 'Authentication-Info';
    }
    public function get_field_value(): string
    {
        return (string) $this->value;
    }
    public function to_string(): string
    {
        return 'Authentication-Info: ' . $this->get_field_value();
    }
}