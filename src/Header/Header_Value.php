<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function ord;
use function strlen;
/**
 * Utility class for validating and filtering HTTP header values.
 *
 * Defends against HTTP response splitting (CRLF injection) attacks by removing
 * or rejecting non-printable characters and control sequences from header values.
 *
 * @see https://owasp.org/www-community/attacks/HTTP_Response_Splitting
 * @since 2.0.0
 */
final class Header_Value
{
    /**
     * Private constructor; non-instantiable.
     */
    private function __construct()
    {
    }
    /**
     * Filter a header value, stripping characters that could enable HTTP response splitting.
     *
     * Removes all characters that are neither visible ASCII (32–126, 128–254)
     * nor a horizontal tab (ASCII 9). CR, LF, NUL, and DEL are silently dropped.
     * Use this for output when you want best-effort sanitisation rather than rejection.
     *
     * @param string $value The raw header value to sanitise
     * @return string The filtered value with unsafe characters removed
     * @see is_valid() To check validity without modifying the value
     * @see assert_valid() To throw on invalid values
     * @complexity O(n) where n is the byte length of $value
     * @since 2.0.0
     */
    public static function filter(string $value): string
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
     * Validate a header value per RFC 7230 rules.
     *
     * Returns false if the value contains any character that could be used for
     * HTTP response splitting: CR (13), LF (10), NUL (0), DEL (127), or any byte
     * outside the visible ASCII + horizontal-tab range.
     *
     * @param string $value The header value to validate
     * @return bool True if every byte in $value is a valid RFC 7230 header character
     * @see filter() To strip invalid characters instead of rejecting the value
     * @since 2.0.0
     */
    public static function is_valid(string $value): bool
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
     * Assert that a header value is valid, throwing if it is not.
     *
     * Preferred over is_valid() when invalid input should be a hard failure
     * rather than a boolean check, e.g. when setting a response header.
     *
     * @param string $value The header value to assert is valid
     * @throws Exception\InvalidArgumentException If $value contains any invalid character
     * @see is_valid() For the non-throwing validation check
     * @since 2.0.0
     */
    public static function assert_valid(string $value): void
    {
        if (!self::is_valid($value)) {
            throw new Exception\InvalidArgumentException('Invalid header value');
        }
    }
}