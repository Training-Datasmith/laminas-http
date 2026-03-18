<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function sprintf;
use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.13
 *
 * @throws Exception\InvalidArgumentException
 */
class ContentLength implements HeaderInterface
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
        if (strtolower($name) !== 'content-length') {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid header line for Content-Length string: "%s"',
                $name
            ));
        }

        // @todo implementation details
        return new static($value);
    }

    /** @param null|string $value */
    public function __construct($value = null)
    {
        if (null !== $value) {
            HeaderValue::assertValid($value);
            $this->value = $value;
        }
    }

    public function getFieldName(): string
    {
        return 'Content-Length';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'Content-Length: ' . $this->getFieldValue();
    }
}
