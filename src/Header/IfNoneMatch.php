<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function sprintf;
use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.26
 *
 * @throws Exception\InvalidArgumentException
 */
class IfNoneMatch implements HeaderInterface
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
        if (strtolower($name) !== 'if-none-match') {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid header line for If-None-Match string: "%s"',
                $name
            ));
        }

        // @todo implementation details
        return new static($value);
    }

    /** @param string $value */
    public function __construct($value = null)
    {
        if ($value !== null) {
            HeaderValue::assertValid($value);
            $this->value = $value;
        }
    }

    public function getFieldName(): string
    {
        return 'If-None-Match';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'If-None-Match: ' . $this->getFieldValue();
    }
}
