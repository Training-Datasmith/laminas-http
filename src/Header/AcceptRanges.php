<?php

namespace Laminas\Http\Header;

use function strtolower;

/**
 * Accept Ranges Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.5
 */
class AcceptRanges implements HeaderInterface
{
    /** @var null|string */
    protected $rangeUnit;

    /**
     * @param string $headerLine
     */
    public static function fromString($headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine($headerLine);

        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'accept-ranges') {
            throw new Exception\InvalidArgumentException(
                'Invalid header line for Accept-Ranges string'
            );
        }

        return new static($value);
    }

    /** @param null|string $rangeUnit */
    public function __construct($rangeUnit = null)
    {
        if ($rangeUnit !== null) {
            $this->setRangeUnit($rangeUnit);
        }
    }

    public function getFieldName(): string
    {
        return 'Accept-Ranges';
    }

    /** @return string */
    public function getFieldValue()
    {
        return $this->getRangeUnit();
    }

    /**
     * @param string $rangeUnit
     */
    public function setRangeUnit($rangeUnit): static
    {
        HeaderValue::assertValid($rangeUnit);
        $this->rangeUnit = $rangeUnit;
        return $this;
    }

    public function getRangeUnit(): string
    {
        return (string) $this->rangeUnit;
    }

    public function toString(): string
    {
        return 'Accept-Ranges: ' . $this->getFieldValue();
    }
}
