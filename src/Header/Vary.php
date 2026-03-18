<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.44
 *
 * @throws Exception\InvalidArgumentException
 */
class Vary implements HeaderInterface
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
        if (strtolower($name) !== 'vary') {
            throw new Exception\InvalidArgumentException('Invalid header line for Vary string: "' . $name . '"');
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
        return 'Vary';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'Vary: ' . $this->getFieldValue();
    }
}
