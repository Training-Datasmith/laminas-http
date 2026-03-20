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
     * Create a header instance by parsing a raw header line string.
     *
     * @param string $header_line A raw HTTP header line in the form "Name: Value"
     * @return static The instantiated header object
     * @throws Exception\InvalidArgumentException If the line format or value is invalid
     * @since 2.0.0
     */
    public static function from_string(string $header_line): static
    {
        [$field_name, $field_value] = self::split_header_line($header_line);
        return new static($field_name, $field_value);
    }
    /**
     * Split a raw header line into its name and value parts.
     *
     * @param string $header_line A raw header line in "Name: Value" format
     * @return array{0: string, 1: string} Index 0 is the field name, index 1 is the field value
     * @throws Exception\InvalidArgumentException If the line does not contain a colon separator
     *         or if the value portion fails RFC 7230 validation
     * @since 2.0.0
     */
    public static function split_header_line(string $header_line): array
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
     * Construct a generic HTTP header with an optional name and value.
     *
     * @param string|null $field_name  The header field name (RFC 7230 token); null to set later
     * @param string|null $field_value The header field value; null to set later
     * @throws Exception\InvalidArgumentException If $field_name is not a valid RFC 7230 token
     * @throws Exception\InvalidArgumentException If $field_value fails RFC 7230 validation
     * @since 2.0.0
     */
    public function __construct(?string $field_name = null, ?string $field_value = null)
    {
        if ($field_name) {
            $this->set_field_name($field_name);
        }
        if ($field_value !== null) {
            $this->set_field_value($field_value);
        }
    }
    /**
     * Set the header field name.
     *
     * Must be a valid RFC 7230 token: only tchar characters are permitted
     * (alphanumerics, and the symbols !#$%&'*+-.^_`|~).
     *
     * @param string $field_name The header field name to set
     * @return static Fluent interface
     * @throws Exception\InvalidArgumentException If $field_name is empty or not a valid RFC 7230 token
     * @since 2.0.0
     */
    public function set_field_name(string $field_name): static
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
     * Retrieve the header field name.
     *
     * @return string The RFC 7230 token used as the header field name
     * @since 2.0.0
     */
    public function get_field_name(): string
    {
        return $this->field_name;
    }
    /**
     * Set the header field value.
     *
     * The value is validated against RFC 7230 rules — CRLF injection characters
     * will cause an exception. Whitespace-only values are normalised to empty string.
     *
     * @param string $field_value The raw header field value to set
     * @return static Fluent interface
     * @throws Exception\InvalidArgumentException If $field_value contains invalid characters
     * @since 2.0.0
     */
    public function set_field_value(string $field_value): static
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
     * Retrieve the header field value.
     *
     * @return string The validated, normalised header field value
     * @since 2.0.0
     */
    public function get_field_value(): string
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