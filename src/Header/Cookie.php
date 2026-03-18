<?php

namespace Laminas\Http\Header;

use ArrayObject;

use function array_key_exists;
use function array_merge;
use function count;
use function explode;
use function implode;
use function is_array;
use function preg_split;
use function sprintf;
use function strtolower;
use function urldecode;
use function urlencode;

/**
 * @see http://www.ietf.org/rfc/rfc2109.txt
 * @see http://www.w3.org/Protocols/rfc2109/rfc2109
 */
class Cookie extends ArrayObject implements HeaderInterface, \Stringable
{
    /** @var bool */
    protected $encodeValue = true;

    /**
     * @param SetCookie[] $setCookieClass
     */
    public static function fromSetCookieArray(array $setCookies): static
    {
        $nvPairs = [];

        foreach ($setCookies as $setCookie) {
            if (! $setCookie instanceof SetCookie) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s requires an array of SetCookie objects',
                    __METHOD__
                ));
            }

            if (array_key_exists($setCookie->getName(), $nvPairs)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Two cookies with the same name were provided to %s',
                    __METHOD__
                ));
            }

            $nvPairs[$setCookie->getName()] = $setCookie->getValue();
        }

        return new static($nvPairs);
    }

    /**
     * @param string $headerLine
     */
    public static function fromString($headerLine): static
    {
        $header = new static();

        [$name, $value] = GenericHeader::splitHeaderLine($headerLine);

        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'cookie') {
            throw new Exception\InvalidArgumentException('Invalid header line for Server string: "' . $name . '"');
        }

        $nvPairs = preg_split('#;\s*#', $value);

        $arrayInfo = [];
        foreach ($nvPairs as $nvPair) {
            $parts = explode('=', $nvPair, 2);
            if (count($parts) !== 2) {
                throw new Exception\RuntimeException('Malformed Cookie header found');
            }
            [$name, $value]   = $parts;
            $arrayInfo[$name] = urldecode($value);
        }

        $header->exchangeArray($arrayInfo);

        return $header;
    }

    public function __construct(array $array = [])
    {
        parent::__construct($array, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @param bool $encodeValue
     * @return $this
     */
    public function setEncodeValue($encodeValue): static
    {
        $this->encodeValue = (bool) $encodeValue;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEncodeValue()
    {
        return $this->encodeValue;
    }

    public function getFieldName(): string
    {
        return 'Cookie';
    }

    public function getFieldValue(): string
    {
        $nvPairs = [];

        foreach ($this->flattenCookies($this) as $name => $value) {
            $nvPairs[] = $name . '=' . ($this->encodeValue ? urlencode($value) : $value);
        }

        return implode('; ', $nvPairs);
    }

    /**
     * @param iterable<string, string> $data
     * @param null|string $prefix
     * @return array<string, string>
     */
    protected function flattenCookies($data, $prefix = null): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $key = $prefix ? $prefix . '[' . $key . ']' : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenCookies($value, $key));
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function toString(): string
    {
        return 'Cookie: ' . $this->getFieldValue();
    }

    /**
     * Get the cookie as a string, suitable for sending as a "Cookie" header in an
     * HTTP request
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
