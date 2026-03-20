<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function explode;
use Laminas\Uri\Uri_Factory;
use function strtolower;
/**
 * @see http://tools.ietf.org/id/draft-abarth-origin-03.html#rfc.section.2
 *
 * @throws Exception\InvalidArgumentException
 */
class Origin implements Header_Interface
{
    /** @var string */
    protected $value = '';
    /**
     * @param string $headerLine
     */
    public static function from_string($header_line): static
    {
        [$name, $value] = explode(': ', $header_line, 2);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'origin') {
            throw new Exception\InvalidArgumentException('Invalid header line for Origin string: "' . $name . '"');
        }
        $uri = Uri_Factory::factory($value);
        if (!$uri->is_valid()) {
            throw new Exception\InvalidArgumentException('Invalid header value for Origin key: "' . $name . '"');
        }
        return new static($value);
    }
    /**
     * @param string|null $value
     */
    public function __construct($value = null)
    {
        if ($value !== null) {
            Header_Value::assert_valid($value);
            $this->value = $value;
        }
    }
    public function get_field_name(): string
    {
        return 'Origin';
    }
    public function get_field_value(): string
    {
        return (string) $this->value;
    }
    public function to_string(): string
    {
        return 'Origin: ' . $this->get_field_value();
    }
}