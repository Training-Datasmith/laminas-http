<?php

declare(strict_types=1);

namespace Laminas_Test\Http\Header;

use Laminas\Http\Header\Exception\InvalidArgumentException;
use Laminas\Http\Header\Header_Value;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Security regression tests for Header_Value — CRLF / HTTP response splitting prevention.
 *
 * These tests validate that the header value filtering and validation methods
 * correctly reject or strip characters that could be used for HTTP response
 * splitting attacks (CVE / ZF2015-04 class).
 *
 * @see https://owasp.org/www-community/attacks/HTTP_Response_Splitting
 */
class Header_Value_Security_Test extends TestCase
{
    // -----------------------------------------------------------------------
    // filter() — strips injection characters rather than throwing
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function crlf_injection_filter_provider(): array
    {
        return [
            'LF stripped'                    => ["Value\nInjected-Header: evil", 'ValueInjected-Header: evil'],
            'CR stripped'                    => ["Value\rInjected-Header: evil", 'ValueInjected-Header: evil'],
            'CRLF stripped'                  => ["Value\r\nInjected-Header: evil", 'ValueInjected-Header: evil'],
            'double CRLF stripped'           => ["Value\r\n\r\nBody injection", 'ValueBody injection'],
            'NUL byte stripped'              => ["Value\x00Injected", 'ValueInjected'],
            'DEL byte stripped'              => ["Value\x7FInjected", 'ValueInjected'],
            'null byte mid-value stripped'   => ["leg\x00it", 'legit'],
            'horizontal tab preserved'       => ["Value\twith\ttabs", "Value\twith\ttabs"],
            'plain ASCII preserved'          => ['Content-Disposition: attachment', 'Content-Disposition: attachment'],
        ];
    }

    #[DataProvider('crlf_injection_filter_provider')]
    #[Group('security')]
    #[Group('crlf-injection')]
    public function test_filter_strips_response_splitting_vectors(string $input, string $expected): void
    {
        self::assertSame($expected, Header_Value::filter($input));
    }

    // -----------------------------------------------------------------------
    // is_valid() — returns false for any injection vector
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalid_header_value_provider(): array
    {
        return [
            'LF character'                           => ["value\nInjected"],
            'CR character'                           => ["value\rInjected"],
            'CRLF sequence'                          => ["value\r\nInjected"],
            'double CRLF (HTTP response splitting)'  => ["value\r\n\r\nInjected body"],
            'NUL byte'                               => ["value\x00end"],
            'DEL character'                          => ["value\x7Fend"],
            'byte 255 (out of range)'                => ["value\xFFend"],
            'low ASCII control char 0x01'            => ["value\x01end"],
            'low ASCII BEL 0x07'                     => ["value\x07end"],
            'ESC character 0x1B'                     => ["value\x1Bend"],
        ];
    }

    #[DataProvider('invalid_header_value_provider')]
    #[Group('security')]
    #[Group('crlf-injection')]
    public function test_is_valid_returns_false_for_response_splitting_vectors(string $value): void
    {
        self::assertFalse(Header_Value::is_valid($value));
    }

    // -----------------------------------------------------------------------
    // is_valid() — returns true for safe values
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function valid_header_value_provider(): array
    {
        return [
            'plain ASCII text'            => ['application/json'],
            'text with spaces'            => ['no-cache, no-store'],
            'text with horizontal tab'    => ["value\twith\ttab"],
            'quoted string'               => ['"filename.txt"'],
            'high Latin-1 visible byte'   => ["\xE9"],  // é in ISO-8859-1
            'boundary-like value'         => ['----WebKitFormBoundary7MA4YWxkTrZu0gW'],
        ];
    }

    #[DataProvider('valid_header_value_provider')]
    #[Group('security')]
    public function test_is_valid_accepts_safe_header_values(string $value): void
    {
        self::assertTrue(Header_Value::is_valid($value));
    }

    // -----------------------------------------------------------------------
    // assert_valid() — throws on injection vectors
    // -----------------------------------------------------------------------

    #[Group('security')]
    #[Group('crlf-injection')]
    public function test_assert_valid_throws_on_crlf_injection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Header_Value::assert_valid("Location: http://evil.com\r\n");
    }

    #[Group('security')]
    #[Group('crlf-injection')]
    public function test_assert_valid_throws_on_lf_only_injection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Header_Value::assert_valid("value\nX-Injected: true");
    }

    #[Group('security')]
    #[Group('crlf-injection')]
    public function test_assert_valid_throws_on_nul_byte(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Header_Value::assert_valid("value\x00injected");
    }

    #[Group('security')]
    public function test_assert_valid_accepts_safe_value(): void
    {
        // Must not throw
        Header_Value::assert_valid('application/json; charset=utf-8');
        $this->addToAssertionCount(1);
    }

    // -----------------------------------------------------------------------
    // filter() is idempotent
    // -----------------------------------------------------------------------

    #[Group('security')]
    public function test_filter_is_idempotent_on_safe_values(): void
    {
        $safe = 'Content-Type: text/html; charset=utf-8';
        self::assertSame($safe, Header_Value::filter($safe));
    }

    #[Group('security')]
    #[Group('crlf-injection')]
    public function test_filter_is_idempotent_after_first_pass(): void
    {
        $malicious = "value\r\nX-Injected: true";
        $once      = Header_Value::filter($malicious);
        $twice     = Header_Value::filter($once);
        self::assertSame($once, $twice);
    }
}
