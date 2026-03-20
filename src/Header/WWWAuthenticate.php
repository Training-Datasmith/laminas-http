<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function implode;
use function sprintf;
use function strtolower;
/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.47
 *
 * @throws Exception\InvalidArgumentException
 */
class Www_Authenticate implements Multiple_Header_Interface
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
        if (strtolower($name) !== 'www-authenticate') {
            throw new Exception\InvalidArgumentException(sprintf('Invalid header line for WWW-Authenticate string: "%s"', $name));
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
        return 'WWW-Authenticate';
    }
    public function get_field_value(): string
    {
        return (string) $this->value;
    }
    public function to_string(): string
    {
        return 'WWW-Authenticate: ' . $this->get_field_value();
    }
    public function to_string_multiple_headers(array $headers): string
    {
        $strings = [$this->to_string()];
        foreach ($headers as $header) {
            if (!$header instanceof Www_Authenticate) {
                throw new Exception\RuntimeException('The WWWAuthenticate multiple header implementation can only' . ' accept an array of WWWAuthenticate headers');
            }
            $strings[] = $header->to_string();
        }
        return implode("\r\n", $strings);
    }
}