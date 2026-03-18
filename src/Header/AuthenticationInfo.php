<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function sprintf;
use function strtolower;

/**
 * @see http://www.ietf.org/rfc/rfc2617.txt
 *
 * @throws Exception\InvalidArgumentException
 */
class AuthenticationInfo implements HeaderInterface
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
        if (strtolower($name) !== 'authentication-info') {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid header line for Authentication-Info string: "%s"',
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
        return 'Authentication-Info';
    }

    public function getFieldValue(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return 'Authentication-Info: ' . $this->getFieldValue();
    }
}
