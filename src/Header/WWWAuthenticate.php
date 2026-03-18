<?php

namespace Laminas\Http\Header;

use function implode;
use function sprintf;
use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.47
 *
 * @throws Exception\InvalidArgumentException
 */
class WWWAuthenticate implements MultipleHeaderInterface
{
    /** @var string */
    protected $value;

    /**
     * @param string $headerLine
     */
    public static function fromString($headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine($headerLine);

        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'www-authenticate') {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid header line for WWW-Authenticate string: "%s"',
                $name
            ));
        }

        // @todo implementation details
        return new static($value);
    }

    /** @param null|string $value */
    public function __construct($value = null)
    {
        if ($value !== null) {
            HeaderValue::assertValid($value);
            $this->value = $value;
        }
    }

    public function getFieldName(): string
    {
        return 'WWW-Authenticate';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'WWW-Authenticate: ' . $this->getFieldValue();
    }

    public function toStringMultipleHeaders(array $headers): string
    {
        $strings = [$this->toString()];
        foreach ($headers as $header) {
            if (! $header instanceof WWWAuthenticate) {
                throw new Exception\RuntimeException(
                    'The WWWAuthenticate multiple header implementation can only'
                    . ' accept an array of WWWAuthenticate headers'
                );
            }
            $strings[] = $header->toString();
        }
        return implode("\r\n", $strings);
    }
}
