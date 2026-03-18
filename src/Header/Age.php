<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function is_int;
use function is_numeric;

use const PHP_INT_MAX;

use function strtolower;

/**
 * Age HTTP Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.6
 */
class Age implements HeaderInterface
{
    /**
     * Estimate of the amount of time in seconds since the response
     *
     * @var int
     */
    protected $deltaSeconds;

    /**
     * Create Age header from string
     *
     * @param string $headerLine
     * @throws Exception\InvalidArgumentException
     */
    public static function fromString($headerLine): static
    {
        [$name, $value] = GenericHeader::splitHeaderLine($headerLine);

        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'age') {
            throw new Exception\InvalidArgumentException('Invalid header line for Age string: "' . $name . '"');
        }

        return new static($value);
    }

    /** @param null|int $deltaSeconds */
    public function __construct($deltaSeconds = null)
    {
        if ($deltaSeconds !== null) {
            $this->setDeltaSeconds($deltaSeconds);
        }
    }

    /**
     * Get header name
     */
    public function getFieldName(): string
    {
        return 'Age';
    }

    /**
     * Get header value (number of seconds)
     */
    public function getFieldValue(): string
    {
        return (string) $this->getDeltaSeconds();
    }

    /**
     * Set number of seconds
     *
     * @param int $delta
     * @return $this
     */
    public function setDeltaSeconds($delta): static
    {
        if (! is_int($delta) && ! is_numeric($delta)) {
            throw new Exception\InvalidArgumentException('Invalid delta provided');
        }
        $this->deltaSeconds = (int) $delta;
        return $this;
    }

    /**
     * Get number of seconds
     *
     * @return int
     */
    public function getDeltaSeconds()
    {
        return $this->deltaSeconds;
    }

    /**
     * Return header line
     * In case of overflow RFC states to set value of 2147483648 (2^31)
     */
    public function toString(): string
    {
        return 'Age: ' . ($this->deltaSeconds >= PHP_INT_MAX ? '2147483648' : $this->deltaSeconds);
    }
}
