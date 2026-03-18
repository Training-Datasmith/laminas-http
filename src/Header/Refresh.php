<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function strtolower;

/**
 * @throws Exception\InvalidArgumentException
 * @todo FIND SPEC FOR THIS
 */
class Refresh implements HeaderInterface
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
        if (strtolower($name) !== 'refresh') {
            throw new Exception\InvalidArgumentException('Invalid header line for Refresh string: "' . $name . '"');
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
        return 'Refresh';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'Refresh: ' . $this->getFieldValue();
    }
}
