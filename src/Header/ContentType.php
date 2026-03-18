<?php

namespace Laminas\Http\Header;

use stdClass;

use function array_merge;
use function array_shift;
use function array_walk;
use function count;
use function explode;
use function gettype;
use function implode;
use function is_object;
use function is_string;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.17
 *
 * @throws Exception\InvalidArgumentException
 */
class ContentType implements HeaderInterface
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
    public static function fromString($headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine($headerLine);

        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'content-type') {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid header line for Content-Type string: "%s"',
                $name
            ));
        }

        $parts     = explode(';', $value);
        $mediaType = array_shift($parts);
        $header    = new static($value, trim($mediaType));

        if (count($parts) > 0) {
            $parameters = [];
            foreach ($parts as $parameter) {
                $parameter = trim($parameter);
                if (! preg_match('/^(?P<key>[^\s\=]+)\="?(?P<value>[^\s\"]*)"?$/', $parameter, $matches)) {
                    continue;
                }
                $parameters[$matches['key']] = $matches['value'];
            }
            $header->setParameters($parameters);
        }

        return $header;
    }

    /**
     * @param null|string $value
     * @param null|string $mediaType
     */
    public function __construct($value = null, protected $mediaType = null)
    {
        if ($value !== null) {
            HeaderValue::assertValid($value);
            $this->value = $value;
        }
    }

    /**
     * Determine if the mediatype value in this header matches the provided criteria
     *
     * @param  array|string $matchAgainst
     * @return string|bool Matched value or false
     */
    public function match($matchAgainst)
    {
        if (is_string($matchAgainst)) {
            $matchAgainst = $this->splitMediaTypesFromString($matchAgainst);
        }

        $mediaType = $this->getMediaType();
        $left      = $this->getMediaTypeObjectFromString($mediaType);

        foreach ($matchAgainst as $matchType) {
            $matchType = strtolower((string) $matchType);

            if ($mediaType === $matchType) {
                return $matchType;
            }

            $right = $this->getMediaTypeObjectFromString($matchType);

            // Is the right side a wildcard type?
            if ($right->type === '*') {
                if ($this->validateSubtype($right, $left)) {
                    return $matchType;
                }
            }

            // Do the types match?
            if ($right->type === $left->type) {
                if ($this->validateSubtype($right, $left)) {
                    return $matchType;
                }
            }
        }

        return false;
    }

    /**
     * Create a string representation of the header
     */
    public function toString(): string
    {
        return 'Content-Type: ' . $this->getFieldValue();
    }

    /**
     * Get the field name
     */
    public function getFieldName(): string
    {
        return 'Content-Type';
    }

    /**
     * Get the field value
     *
     * @return string
     */
    public function getFieldValue()
    {
        if (null !== $this->value) {
            return (string) $this->value;
        }
        return $this->assembleValue();
    }

    /**
     * Set the media type
     *
     * @param  string $mediaType
     * @return $this
     */
    public function setMediaType($mediaType): static
    {
        HeaderValue::assertValid($mediaType);
        $this->mediaType = strtolower($mediaType);
        $this->value     = null;
        return $this;
    }

    /**
     * Get the media type
     */
    public function getMediaType(): string
    {
        return (string) $this->mediaType;
    }

    /**
     * Set additional content-type parameters
     *
     * @return $this
     */
    public function setParameters(array $parameters): static
    {
        foreach ($parameters as $key => $value) {
            HeaderValue::assertValid($key);
            HeaderValue::assertValid($value);
        }
        $this->parameters = array_merge($this->parameters, $parameters);
        $this->value      = null;
        return $this;
    }

    /**
     * Get any additional content-type parameters currently set
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Set the content-type character set encoding
     *
     * @param  string $charset
     * @return $this
     */
    public function setCharset($charset): static
    {
        HeaderValue::assertValid($charset);
        $this->parameters['charset'] = $charset;
        $this->value                 = null;
        return $this;
    }

    /**
     * Get the content-type character set encoding, if any
     *
     * @return null|string
     */
    public function getCharset()
    {
        return $this->parameters['charset'] ?? null;
    }

    /**
     * Assemble the value based on the media type and any available parameters
     *
     * @return string
     */
    protected function assembleValue()
    {
        $mediaType = $this->getMediaType();
        if (empty($this->parameters)) {
            return $mediaType;
        }

        $parameters = [];
        foreach ($this->parameters as $key => $value) {
            $parameters[] = sprintf('%s=%s', $key, $value);
        }

        return sprintf('%s; %s', $mediaType, implode('; ', $parameters));
    }

    /**
     * Split comma-separated media types into an array
     *
     * @param  string $criteria
     */
    protected function splitMediaTypesFromString($criteria): array
    {
        $mediaTypes = explode(',', $criteria);
        array_walk(
            $mediaTypes,
            function (&$value): void {
                $value = trim($value);
            }
        );

        return $mediaTypes;
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
    protected function getMediaTypeObjectFromString($string)
    {
        if (! is_string($string)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Non-string mediatype "%s" provided',
                get_debug_type($string)
            ));
        }

        $parts = explode('/', $string, 2);
        if (1 === count($parts)) {
            throw new Exception\DomainException(sprintf(
                'Invalid mediatype "%s" provided',
                $string
            ));
        }

        $type    = array_shift($parts);
        $subtype = array_shift($parts);
        $format  = $subtype;
        if (str_contains((string) $subtype, '+')) {
            $parts   = explode('+', (string) $subtype, 2);
            $subtype = array_shift($parts);
            $format  = array_shift($parts);
        }

        return (object) [
            'type'    => $type,
            'subtype' => $subtype,
            'format'  => $format,
        ];
    }

    /**
     * Validate a subtype
     *
     * @param  stdClass $right
     * @param  stdClass $left
     * @return bool
     */
    protected function validateSubtype($right, $left)
    {
        // Is the right side a wildcard subtype?
        if ($right->subtype === '*') {
            return $this->validateFormat($right, $left);
        }

        // Do the right side and left side subtypes match?
        if ($right->subtype === $left->subtype) {
            return $this->validateFormat($right, $left);
        }

        // Is the right side a partial wildcard?
        if (str_ends_with((string) $right->subtype, '*')) {
            // validate partial-wildcard subtype
            if (! $this->validatePartialWildcard($right->subtype, $left->subtype)) {
                return false;
            }
            // Finally, verify format is valid
            return $this->validateFormat($right, $left);
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
    protected function validateFormat($right, $left): bool
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
    protected function validatePartialWildcard($right, $left): bool
    {
        $requiredSegment = substr($right, 0, strlen($right) - 1);
        if ($requiredSegment === $left) {
            return true;
        }

        if (strlen($requiredSegment) >= strlen($left)) {
            return false;
        }

        if (str_starts_with($left, $requiredSegment)) {
            return true;
        }

        return false;
    }
}
