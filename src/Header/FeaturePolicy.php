<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_pad;
use function array_walk;
use function explode;
use function implode;
use function in_array;
use function sprintf;
use function strcasecmp;
use function trim;
/**
 * Feature Policy (based on Editor’s Draft, 28 November 2019)
 *
 * @link https://w3c.github.io/webappsec-feature-policy/
 */
class Feature_Policy implements Header_Interface
{
    /**
     * Valid directive names
     *
     * @see https://github.com/w3c/webappsec-feature-policy/blob/master/features.md
     *
     * @var string[]
     */
    protected $valid_directive_names = [
        // Standardized Features
        'accelerometer',
        'ambient-light-sensor',
        'autoplay',
        'battery',
        'camera',
        'display-capture',
        'document-domain',
        'fullscreen',
        'execution-while-not-rendered',
        'execution-while-out-of-viewport',
        'gyroscope',
        'magnetometer',
        'microphone',
        'midi',
        'payment',
        'picture-in-picture',
        'sync-xhr',
        'usb',
        'wake-lock',
        'xr',
        // Proposed Features
        'encrypted-media',
        'geolocation',
        'speaker',
        // Experimental Features
        'document-write',
        'font-display-late-swap',
        'layout-animations',
        'loading-frame-default-eager',
        'loading-image-default-eager',
        'legacy-image-formats',
        'oversized-images',
        'sync-script',
        'unoptimized-lossy-images',
        'unoptimized-lossless-images',
        'unsized-media',
        'vertical-scroll',
        'serial',
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
     * @param string $name The directive name.
     * @param string[] $sources The source list.
     * @return $this
     * @throws Exception\InvalidArgumentException If the name is not a valid directive name.
     */
    public function set_directive($name, array $sources): static
    {
        if (!in_array($name, $this->valid_directive_names, true)) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects a valid directive name; received "%s"', __METHOD__, (string) $name));
        }
        if (empty($sources)) {
            $this->directives[$name] = "'none'";
            return $this;
        }
        array_walk($sources, [__NAMESPACE__ . '\HeaderValue', 'assertValid']);
        $this->directives[$name] = implode(' ', $sources);
        return $this;
    }
    /**
     * Create Feature Policy header from a given header line
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
        // As per https://w3c.github.io/webappsec-feature-policy/#algo-parse-policy-directive
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
        return 'Feature-Policy';
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
        return implode(' ', $directives);
    }
    /**
     * Return the header as a string
     */
    public function to_string(): string
    {
        return sprintf('%s: %s', $this->get_field_name(), $this->get_field_value());
    }
}