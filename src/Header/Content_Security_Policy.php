<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_pad;
use function array_walk;
use function explode;
use function implode;
use function in_array;
use function sprintf;
use function str_replace;
use function strcasecmp;
use function trim;
/**
 * Content Security Policy Level 3 Header
 *
 * @link http://www.w3.org/TR/CSP/
 */
class Content_Security_Policy implements Multiple_Header_Interface
{
    /**
     * Valid directive names
     *
     * @var array
     */
    protected $valid_directive_names = [
        // As per http://www.w3.org/TR/CSP/#directives
        // Fetch directives
        'child-src',
        'connect-src',
        'default-src',
        'font-src',
        'frame-src',
        'img-src',
        'manifest-src',
        'media-src',
        'object-src',
        'prefetch-src',
        'script-src',
        'script-src-elem',
        'script-src-attr',
        'style-src',
        'style-src-elem',
        'style-src-attr',
        'worker-src',
        // Document directives
        'base-uri',
        'plugin-types',
        'sandbox',
        // Navigation directives
        'form-action',
        'frame-ancestors',
        'navigate-to',
        // Reporting directives
        'report-uri',
        'report-to',
        // Other directives
        'block-all-mixed-content',
        'require-sri-for',
        'require-trusted-types-for',
        'trusted-types',
        'upgrade-insecure-requests',
    ];
    /**
     * The directives defined for this policy
     *
     * @var array
     */
    protected $directives = [];
    /**
     * Get the list of defined directives
     *
     * @return array
     */
    public function get_directives()
    {
        return $this->directives;
    }
    /**
     * Sets the directive to consist of the source list
     *
     * Reverses http://www.w3.org/TR/CSP/#parsing-1
     *
     * @param string $name The directive name.
     * @param array $sources The source list.
     * @return $this
     * @throws Exception\InvalidArgumentException If the name is not a valid directive name.
     */
    public function set_directive($name, array $sources): static
    {
        if (!in_array($name, $this->valid_directive_names, true)) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects a valid directive name; received "%s"', __METHOD__, (string) $name));
        }
        if ($name === 'block-all-mixed-content' || $name === 'upgrade-insecure-requests') {
            if ($sources) {
                throw new Exception\InvalidArgumentException(sprintf('Received value for %s directive; none expected', $name));
            }
            $this->directives[$name] = '';
            return $this;
        }
        if (empty($sources)) {
            if ('report-uri' === $name) {
                if (isset($this->directives[$name])) {
                    unset($this->directives[$name]);
                }
                return $this;
            }
            $this->directives[$name] = "'none'";
            return $this;
        }
        array_walk($sources, [__NAMESPACE__ . '\HeaderValue', 'assertValid']);
        $this->directives[$name] = implode(' ', $sources);
        return $this;
    }
    /**
     * Create Content Security Policy header from a given header line
     *
     * @param string $headerLine The header line to parse.
     * @throws Exception\InvalidArgumentException If the name field in the given header line does not match.
     */
    public static function from_string($header_line): static
    {
        $header = new static();
        $header_name = $header->get_field_name();
        [$name, $value] = Generic_Header::split_header_line($header_line);
        // Ensure the proper header name
        if (strcasecmp($name, $header_name) !== 0) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid header line for %s string: "%s"', $header_name, $name));
        }
        // As per http://www.w3.org/TR/CSP/#parsing
        $tokens = explode(';', $value);
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token) {
                [$directive_name, $directive_value] = array_pad(explode(' ', $token, 2), 2, null);
                if (!isset($header->directives[$directive_name])) {
                    $header->set_directive($directive_name, $directive_value === null ? [] : [$directive_value]);
                }
            }
        }
        return $header;
    }
    /**
     * Get the header name
     */
    public function get_field_name(): string
    {
        return 'Content-Security-Policy';
    }
    /**
     * Get the header value
     */
    public function get_field_value(): string
    {
        $directives = [];
        foreach ($this->directives as $name => $value) {
            $directives[] = sprintf('%s %s;', $name, $value);
        }
        return str_replace(' ;', ';', implode(' ', $directives));
    }
    /**
     * Return the header as a string
     */
    public function to_string(): string
    {
        return sprintf('%s: %s', $this->get_field_name(), $this->get_field_value());
    }
    public function to_string_multiple_headers(array $headers): string
    {
        $strings = [$this->to_string()];
        foreach ($headers as $header) {
            if (!$header instanceof Content_Security_Policy) {
                throw new Exception\RuntimeException('The ContentSecurityPolicy multiple header implementation can only' . ' accept an array of ContentSecurityPolicy headers');
            }
            $strings[] = $header->to_string();
        }
        return implode("\r\n", $strings) . "\r\n";
    }
}