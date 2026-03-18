<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function str_replace;
use function strtolower;

/**
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.43
 *
 * @throws Exception\InvalidArgumentException
 */
class UserAgent implements HeaderInterface
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
        if (str_replace(['_', ' ', '.'], '-', strtolower($name)) !== 'user-agent') {
            throw new Exception\InvalidArgumentException('Invalid header line for User-Agent string: "' . $name . '"');
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
        return 'User-Agent';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'User-Agent: ' . $this->getFieldValue();
    }
}
