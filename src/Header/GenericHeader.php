<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function count;
use function explode;
use function is_string;
use function ltrim;
use function preg_match;
/**
 * Content-Location Header
 */
class Generic_Header implements Header_Interface
{
    /** @var string */
    protected $field_name;
    /** @var string */
    protected $field_value;
    /**
     * Factory to generate a header object from a string
     *
     * @param string $headerLine
     */
    public static function from_string($header_line): static
    {
        [$field_name, $field_value] = self::split_header_line($header_line);
        return new static($field_name, $field_value);
    }
    /**
     * Splits the header line in `name` and `value` parts.
     *
     * @param string $headerLine
     * @return string[] `name` in the first index and `value` in the second.
     * @throws Exception\InvalidArgumentException If header does not match with the format ``name:value``.
     */
    public static function split_header_line($header_line): array
    {
        $parts = explode(':', $header_line, 2);
        if (count($parts) !== 2) {
            throw new Exception\InvalidArgumentException('Header must match with the format "name:value"');
        }
        if (!Header_Value::is_valid($parts[1])) {
            throw new Exception\InvalidArgumentException('Invalid header value detected');
        }
        $parts[1] = ltrim($parts[1]);
        return $parts;
    }
    /**
     * Constructor
     *
     * @param null|string $fieldName
     * @param null|string $fieldValue
     */
    public function __construct($field_name = null, $field_value = null)
    {
        if ($field_name) {
            $this->set_field_name($field_name);
        }
        if ($field_value !== null) {
            $this->set_field_value($field_value);
        }
    }
    /**
     * Set header field name
     *
     * @param  string $fieldName
     * @return $this
     * @throws Exception\InvalidArgumentException If the name does not match with RFC 2616 format.
     */
    public function set_field_name($field_name): static
    {
        if (!is_string($field_name) || empty($field_name)) {
            throw new Exception\InvalidArgumentException('Header name must be a string');
        }
        /*
         * Following RFC 7230 section 3.2
         *
         * header-field = field-name ":" [ field-value ]
         * field-name   = token
         * token        = 1*tchar
         * tchar        = "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "." /
         *                "^" / "_" / "`" / "|" / "~" / DIGIT / ALPHA
         */
        if (!preg_match('/^[!#$%&\'*+\-\.\^_`|~0-9a-zA-Z]+$/', $field_name)) {
            throw new Exception\InvalidArgumentException('Header name must be a valid RFC 7230 (section 3.2) field-name.');
        }
        $this->field_name = $field_name;
        return $this;
    }
    /**
     * Retrieve header field name
     *
     * @return string
     */
    public function get_field_name()
    {
        return $this->field_name;
    }
    /**
     * Set header field value
     *
     * @param  string $fieldValue
     * @return $this
     */
    public function set_field_value($field_value): static
    {
        $field_value = (string) $field_value;
        Header_Value::assert_valid($field_value);
        if (preg_match('/^\s+$/', $field_value)) {
            $field_value = '';
        }
        $this->field_value = $field_value;
        return $this;
    }
    /**
     * Retrieve header field value
     *
     * @return string
     */
    public function get_field_value()
    {
        return $this->field_value;
    }
    /**
     * Cast to string as a well formed HTTP header line
     *
     * Returns in form of "NAME: VALUE\r\n"
     */
    public function to_string(): string
    {
        return $this->get_field_name() . ': ' . $this->get_field_value();
    }
}