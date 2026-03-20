<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function ord;
use function strlen;
final class Header_Value
{
    /**
     * Private constructor; non-instantiable.
     */
    private function __construct()
    {
    }
    /**
     * Filter a header value
     *
     * Ensures CRLF header injection vectors are filtered.
     *
     * Per RFC 7230, only VISIBLE ASCII characters, spaces, and horizontal
     * tabs are allowed in values; only one whitespace character is allowed
     * between visible characters.
     *
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @param string $value
     */
    public static function filter($value): string
    {
        $value = (string) $value;
        $length = strlen($value);
        $string = '';
        for ($i = 0; $i < $length; $i += 1) {
            $ascii = ord($value[$i]);
            // Non-visible, non-whitespace characters
            // 9 === horizontal tab
            // 32-126, 128-254 === visible
            // 127 === DEL
            // 255 === null byte
            if ($ascii < 32 && $ascii !== 9) {
                continue;
            }
            if ($ascii === 127) {
                continue;
            }
            if ($ascii > 254) {
                continue;
            }
            $string .= $value[$i];
        }
        return $string;
    }
    /**
     * Validate a header value.
     *
     * Per RFC 7230, only VISIBLE ASCII characters, spaces, and horizontal
     * tabs are allowed in values; only one whitespace character is allowed
     * between visible characters.
     *
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @param string $value
     */
    public static function is_valid($value): bool
    {
        $value = (string) $value;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i += 1) {
            $ascii = ord($value[$i]);
            // Non-visible, non-whitespace characters
            // 9 === horizontal tab
            // 32-126, 128-254 === visible
            // 127 === DEL
            // 255 === null byte
            if ($ascii < 32 && $ascii !== 9 || $ascii === 127 || $ascii > 254) {
                return false;
            }
        }
        return true;
    }
    /**
     * Assert a header value is valid.
     *
     * @param string $value
     * @throws Exception\RuntimeException For invalid values.
     */
    public static function assert_valid($value): void
    {
        if (!self::is_valid($value)) {
            throw new Exception\InvalidArgumentException('Invalid header value');
        }
    }
}