<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.32
 *
 * @throws Exception\InvalidArgumentException
 */
class Pragma implements HeaderInterface
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
        if (strtolower($name) !== 'pragma') {
            throw new Exception\InvalidArgumentException('Invalid header line for Pragma string: "' . $name . '"');
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
        return 'Pragma';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'Pragma: ' . $this->getFieldValue();
    }
}
