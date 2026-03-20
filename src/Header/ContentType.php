<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_merge;
use function array_shift;
use function array_walk;
use function count;
use function explode;
use function implode;
use function is_string;
use function preg_match;
use function sprintf;
use stdClass;
use function strlen;
use function strtolower;
use function substr;
use function trim;
/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.17
 *
 * @throws Exception\InvalidArgumentException
 */
class Content_Type implements Header_Interface
{
    /** @var array */
    protected $parameters = [];
    /** @var string */
    protected $value;
    /**
     * Factory method: create an object from a string representation
     *
     * @param  string $headerLine
     */
    public static function from_string($header_line): static
    {
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'content-type') {
            throw new Exception\InvalidArgumentException(sprintf('Invalid header line for Content-Type string: "%s"', $name));
        }
        $parts = explode(';', $value);
        $media_type = array_shift($parts);
        $header = new static($value, trim($media_type));
        if (count($parts) > 0) {
            $parameters = [];
            foreach ($parts as $parameter) {
                $parameter = trim($parameter);
                if (!preg_match('/^(?P<key>[^\s\=]+)\="?(?P<value>[^\s\"]*)"?$/', $parameter, $matches)) {
                    continue;
                }
                $parameters[$matches['key']] = $matches['value'];
            }
            $header->set_parameters($parameters);
        }
        return $header;
    }
    /**
     * @param null|string $value
     * @param null|string $mediaType
     */
    public function __construct($value = null, protected $media_type = null)
    {
        if ($value !== null) {
            Header_Value::assert_valid($value);
            $this->value = $value;
        }
    }
    /**
     * Determine if the mediatype value in this header matches the provided criteria
     *
     * @param  array|string $matchAgainst
     * @return string|bool Matched value or false
     */
    public function match($match_against)
    {
        if (is_string($match_against)) {
            $match_against = $this->split_media_types_from_string($match_against);
        }
        $media_type = $this->get_media_type();
        $left = $this->get_media_type_object_from_string($media_type);
        foreach ($match_against as $match_type) {
            $match_type = strtolower((string) $match_type);
            if ($media_type === $match_type) {
                return $match_type;
            }
            $right = $this->get_media_type_object_from_string($match_type);
            // Is the right side a wildcard type?
            if ($right->type === '*') {
                if ($this->validate_subtype($right, $left)) {
                    return $match_type;
                }
            }
            // Do the types match?
            if ($right->type === $left->type) {
                if ($this->validate_subtype($right, $left)) {
                    return $match_type;
                }
            }
        }
        return false;
    }
    /**
     * Create a string representation of the header
     */
    public function to_string(): string
    {
        return 'Content-Type: ' . $this->get_field_value();
    }
    /**
     * Get the field name
     */
    public function get_field_name(): string
    {
        return 'Content-Type';
    }
    /**
     * Get the field value
     *
     * @return string
     */
    public function get_field_value()
    {
        if (null !== $this->value) {
            return (string) $this->value;
        }
        return $this->assemble_value();
    }
    /**
     * Set the media type
     *
     * @param  string $mediaType
     * @return $this
     */
    public function set_media_type($media_type): static
    {
        Header_Value::assert_valid($media_type);
        $this->media_type = strtolower($media_type);
        $this->value = null;
        return $this;
    }
    /**
     * Get the media type
     */
    public function get_media_type(): string
    {
        return (string) $this->media_type;
    }
    /**
     * Set additional content-type parameters
     *
     * @return $this
     */
    public function set_parameters(array $parameters): static
    {
        foreach ($parameters as $key => $value) {
            Header_Value::assert_valid($key);
            Header_Value::assert_valid($value);
        }
        $this->parameters = array_merge($this->parameters, $parameters);
        $this->value = null;
        return $this;
    }
    /**
     * Get any additional content-type parameters currently set
     *
     * @return array
     */
    public function get_parameters()
    {
        return $this->parameters;
    }
    /**
     * Set the content-type character set encoding
     *
     * @param  string $charset
     * @return $this
     */
    public function set_charset($charset): static
    {
        Header_Value::assert_valid($charset);
        $this->parameters['charset'] = $charset;
        $this->value = null;
        return $this;
    }
    /**
     * Get the content-type character set encoding, if any
     *
     * @return null|string
     */
    public function get_charset()
    {
        return $this->parameters['charset'] ?? null;
    }
    /**
     * Assemble the value based on the media type and any available parameters
     *
     * @return string
     */
    protected function assemble_value()
    {
        $media_type = $this->get_media_type();
        if (empty($this->parameters)) {
            return $media_type;
        }
        $parameters = [];
        foreach ($this->parameters as $key => $value) {
            $parameters[] = sprintf('%s=%s', $key, $value);
        }
        return sprintf('%s; %s', $media_type, implode('; ', $parameters));
    }
    /**
     * Split comma-separated media types into an array
     *
     * @param  string $criteria
     */
    protected function split_media_types_from_string($criteria): array
    {
        $media_types = explode(',', $criteria);
        array_walk($media_types, function (&$value): void {
            $value = trim($value);
        });
        return $media_types;
    }
    /**
     * Split a mediatype string into an object with the following parts:
     *
     * - type
     * - subtype
     * - format
     *
     * @param  string $string
     * @return stdClass
     */
    protected function get_media_type_object_from_string($string)
    {
        if (!is_string($string)) {
            throw new Exception\InvalidArgumentException(sprintf('Non-string mediatype "%s" provided', get_debug_type($string)));
        }
        $parts = explode('/', $string, 2);
        if (1 === count($parts)) {
            throw new Exception\DomainException(sprintf('Invalid mediatype "%s" provided', $string));
        }
        $type = array_shift($parts);
        $subtype = array_shift($parts);
        $format = $subtype;
        if (str_contains((string) $subtype, '+')) {
            $parts = explode('+', (string) $subtype, 2);
            $subtype = array_shift($parts);
            $format = array_shift($parts);
        }
        return (object) ['type' => $type, 'subtype' => $subtype, 'format' => $format];
    }
    /**
     * Validate a subtype
     *
     * @param  stdClass $right
     * @param  stdClass $left
     * @return bool
     */
    protected function validate_subtype($right, $left)
    {
        // Is the right side a wildcard subtype?
        if ($right->subtype === '*') {
            return $this->validate_format($right, $left);
        }
        // Do the right side and left side subtypes match?
        if ($right->subtype === $left->subtype) {
            return $this->validate_format($right, $left);
        }
        // Is the right side a partial wildcard?
        if (str_ends_with((string) $right->subtype, '*')) {
            // validate partial-wildcard subtype
            if (!$this->validate_partial_wildcard($right->subtype, $left->subtype)) {
                return false;
            }
            // Finally, verify format is valid
            return $this->validate_format($right, $left);
        }
        // Does the right side subtype match the left side format?
        if ($right->subtype === $left->format) {
            return true;
        }
        // At this point, there is no valid match
        return false;
    }
    /**
     * Validate the format
     *
     * Validate that the right side format matches what the left side defines.
     *
     * @param  string $right
     * @param  string $left
     */
    protected function validate_format($right, $left): bool
    {
        if ($right->format && $left->format) {
            if ($right->format === '*') {
                return true;
            }
            if ($right->format === $left->format) {
                return true;
            }
            return false;
        }
        return true;
    }
    /**
     * Validate a partial wildcard (i.e., string ending in '*')
     *
     * @param  string $right
     * @param  string $left
     */
    protected function validate_partial_wildcard($right, $left): bool
    {
        $required_segment = substr($right, 0, strlen($right) - 1);
        if ($required_segment === $left) {
            return true;
        }
        if (strlen($required_segment) >= strlen($left)) {
            return false;
        }
        if (str_starts_with($left, $required_segment)) {
            return true;
        }
        return false;
    }
}