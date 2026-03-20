<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_key_exists;
use function array_merge;
use ArrayObject;
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
class Cookie extends ArrayObject implements Header_Interface, \Stringable
{
    /** @var bool */
    protected $encode_value = true;
    /**
     * @param SetCookie[] $setCookieClass
     */
    public static function from_set_cookie_array(array $set_cookies): static
    {
        $nv_pairs = [];
        foreach ($set_cookies as $set_cookie) {
            if (!$set_cookie instanceof Set_Cookie) {
                throw new Exception\InvalidArgumentException(sprintf('%s requires an array of SetCookie objects', __METHOD__));
            }
            if (array_key_exists($set_cookie->get_name(), $nv_pairs)) {
                throw new Exception\InvalidArgumentException(sprintf('Two cookies with the same name were provided to %s', __METHOD__));
            }
            $nv_pairs[$set_cookie->get_name()] = $set_cookie->get_value();
        }
        return new static($nv_pairs);
    }
    /**
     * @param string $headerLine
     */
    public static function from_string($header_line): static
    {
        $header = new static();
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'cookie') {
            throw new Exception\InvalidArgumentException('Invalid header line for Server string: "' . $name . '"');
        }
        $nv_pairs = preg_split('#;\s*#', $value);
        $array_info = [];
        foreach ($nv_pairs as $nv_pair) {
            $parts = explode('=', $nv_pair, 2);
            if (count($parts) !== 2) {
                throw new Exception\RuntimeException('Malformed Cookie header found');
            }
            [$name, $value] = $parts;
            $array_info[$name] = urldecode($value);
        }
        $header->exchange_array($array_info);
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
    public function set_encode_value($encode_value): static
    {
        $this->encode_value = (bool) $encode_value;
        return $this;
    }
    /**
     * @return bool
     */
    public function get_encode_value()
    {
        return $this->encode_value;
    }
    public function get_field_name(): string
    {
        return 'Cookie';
    }
    public function get_field_value(): string
    {
        $nv_pairs = [];
        foreach ($this->flatten_cookies($this) as $name => $value) {
            $nv_pairs[] = $name . '=' . ($this->encode_value ? urlencode($value) : $value);
        }
        return implode('; ', $nv_pairs);
    }
    /**
     * @param iterable<string, string> $data
     * @param null|string $prefix
     * @return array<string, string>
     */
    protected function flatten_cookies($data, $prefix = null): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $key = $prefix ? $prefix . '[' . $key . ']' : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten_cookies($value, $key));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    public function to_string(): string
    {
        return 'Cookie: ' . $this->get_field_value();
    }
    /**
     * Get the cookie as a string, suitable for sending as a "Cookie" header in an
     * HTTP request
     */
    public function __toString(): string
    {
        return $this->to_string();
    }
}