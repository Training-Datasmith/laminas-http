<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_key_exists;
use function implode;
use function is_bool;
use function ksort;
use function preg_match;
use function rtrim;
use function sprintf;
use function strlen;
use function strtolower;
use function substr;
use function trim;
/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9
 *
 * @throws Exception\InvalidArgumentException
 */
class Cache_Control implements Header_Interface
{
    /** @var string */
    protected $value;
    /**
     * Array of Cache-Control directives
     *
     * @var array
     */
    protected $directives = [];
    /**
     * Creates a CacheControl object from a headerLine
     *
     * @param string $headerLine
     * @throws Exception\InvalidArgumentException
     */
    public static function from_string($header_line): static
    {
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'cache-control') {
            throw new Exception\InvalidArgumentException(sprintf('Invalid header line for Cache-Control string: "%s"', $name));
        }
        Header_Value::assert_valid($value);
        $directives = static::parse_value($value);
        // @todo implementation details
        $header = new static();
        foreach ($directives as $key => $value) {
            $header->add_directive($key, $value);
        }
        return $header;
    }
    /**
     * Required from HeaderDescription interface
     */
    public function get_field_name(): string
    {
        return 'Cache-Control';
    }
    /**
     * Checks if the internal directives array is empty
     */
    public function is_empty(): bool
    {
        return empty($this->directives);
    }
    /**
     * Add a directive
     * For directives like 'max-age=60', $value = '60'
     * For directives like 'private', use the default $value = true
     *
     * @param string $key
     * @param string|bool $value
     * @return $this
     */
    public function add_directive($key, $value = true): static
    {
        Header_Value::assert_valid($key);
        if (!is_bool($value)) {
            Header_Value::assert_valid($value);
        }
        $this->directives[$key] = $value;
        return $this;
    }
    /**
     * Check the internal directives array for a directive
     *
     * @param string $key
     */
    public function has_directive($key): bool
    {
        return array_key_exists($key, $this->directives);
    }
    /**
     * Fetch the value of a directive from the internal directive array
     *
     * @param string $key
     * @return string|null
     */
    public function get_directive($key)
    {
        return array_key_exists($key, $this->directives) ? $this->directives[$key] : null;
    }
    /**
     * Remove a directive
     *
     * @param string $key
     * @return $this
     */
    public function remove_directive($key): static
    {
        unset($this->directives[$key]);
        return $this;
    }
    /**
     * Assembles the directives into a comma-delimited string
     */
    public function get_field_value(): string
    {
        $parts = [];
        ksort($this->directives);
        foreach ($this->directives as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', (string) $value)) {
                    $value = '"' . $value . '"';
                }
                $parts[] = $key . '=' . $value;
            }
        }
        return implode(', ', $parts);
    }
    /**
     * Returns a string representation of the HTTP Cache-Control header
     */
    public function to_string(): string
    {
        return 'Cache-Control: ' . $this->get_field_value();
    }
    /**
     * Internal function for parsing the value part of a
     * HTTP Cache-Control header
     *
     * @param string $value
     * @throws Exception\InvalidArgumentException
     * @return array
     */
    protected static function parse_value($value)
    {
        $value = trim($value);
        $directives = [];
        // handle empty string early so we don't need a separate start state
        if ($value === '') {
            return $directives;
        }
        $last_match = null;
        // phpcs:disable Generic.PHP.DiscourageGoto.Found
        state_directive:
        switch (static::match(['[a-zA-Z][a-zA-Z_-]*'], $value, $last_match)) {
            case 0:
                $directive = $last_match;
                goto state_value;
            // intentional fall-through
            default:
                throw new Exception\InvalidArgumentException('expected DIRECTIVE');
        }
        state_value:
        switch (static::match(['="[^"]*"', '=[^",\s;]*'], $value, $last_match)) {
            case 0:
                $directives[$directive] = substr($last_match, 2, -1);
                goto state_separator;
            // intentional fall-through
            case 1:
                $directives[$directive] = rtrim(substr($last_match, 1));
                goto state_separator;
            // intentional fall-through
            default:
                $directives[$directive] = true;
                goto state_separator;
        }
        state_separator:
        switch (static::match(['\s*,\s*', '$'], $value, $last_match)) {
            case 0:
                goto state_directive;
            // intentional fall-through
            case 1:
                return $directives;
            default:
                throw new Exception\InvalidArgumentException('expected SEPARATOR or END');
        }
        // phpcs:enable
    }
    /**
     * Internal function used by parseValue to match tokens
     *
     * @param array $tokens
     * @param string $string
     * @param string $lastMatch
     * @return int
     */
    protected static function match($tokens, &$string, &$last_match)
    {
        // Ensure we have a string
        $value = (string) $string;
        foreach ($tokens as $i => $token) {
            if (preg_match('/^' . $token . '/', $value, $matches)) {
                $last_match = $matches[0];
                $string = substr($value, strlen($matches[0]));
                return $i;
            }
        }
        return -1;
    }
}