<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_key_exists;
use function array_pop;
use function count;
use DateTime;
use function gettype;
use function gmdate;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use Laminas\Uri\Uri;
use Laminas\Uri\Uri_Factory;
use function max;
use const PHP_INT_MAX;
use const PHP_INT_SIZE;
use function preg_match;
use function preg_quote;
use function preg_split;
use function sprintf;
use function str_replace;
use function strrpos;
use function strtolower;
use function strtotime;
use function time;
use function urldecode;
use function urlencode;
/**
 * @see http://www.ietf.org/rfc/rfc2109.txt
 * @see http://www.w3.org/Protocols/rfc2109/rfc2109
 *
 * @throws Exception\InvalidArgumentException
 */
class Set_Cookie implements Multiple_Header_Interface
{
    /**
     * Cookie will not be sent for any cross-domain requests whatsoever.
     * Even if the user simply navigates to the target site with a regular link, the cookie will not be sent.
     */
    public const SAME_SITE_STRICT = 'Strict';
    /**
     * Cookie will not be passed for any cross-domain requests unless it's a regular link that navigates user
     * to the target site.
     * Other requests methods (such as POST and PUT) and XHR requests will not contain this cookie.
     */
    public const SAME_SITE_LAX = 'Lax';
    /**
     * Cookie will be sent with same-site and cross-site requests.
     */
    public const SAME_SITE_NONE = 'None';
    /**
     * @internal
     */
    public const SAME_SITE_ALLOWED_VALUES = ['strict' => self::SAME_SITE_STRICT, 'lax' => self::SAME_SITE_LAX, 'none' => self::SAME_SITE_NONE];
    /**
     * @deprecated This property is deprecated, and will be removed
     *
     * @var string
     */
    public $type;
    /**
     * Cookie name
     *
     * @var string|null
     */
    protected $name;
    /**
     * Cookie value
     *
     * @var string|null
     */
    protected $value;
    /**
     * Version
     *
     * @var int|null
     */
    protected $version;
    /**
     * Max Age
     *
     * @var int|null
     */
    protected $max_age;
    /**
     * Cookie expiry date
     *
     * @var int|null
     */
    protected $expires;
    /**
     * Cookie domain
     *
     * @var string|null
     */
    protected $domain;
    /**
     * Cookie path
     *
     * @var string|null
     */
    protected $path;
    /**
     * Whether the cookie is secure or not
     *
     * @var bool|null
     */
    protected $secure;
    /**
     * If the value need to be quoted or not
     *
     * @var bool
     */
    protected $quote_field_value = false;
    /** @var bool|null */
    protected $httponly;
    /** @var string|null */
    protected $same_site;
    /** @var bool */
    protected $encode_value = true;
    /**
     * @static
     * @throws Exception\InvalidArgumentException
     * @param  string $headerLine
     * @param  bool $bypassHeaderFieldName
     * @return array|SetCookie
     */
    public static function from_string($header_line, $bypass_header_field_name = false)
    {
        static $set_cookie_processor = null;
        if ($set_cookie_processor === null) {
            $set_cookie_class = static::class;
            $set_cookie_processor = function ($header_line) use ($set_cookie_class) {
                /** @var SetCookie $header */
                $header = new $set_cookie_class();
                $key_value_pairs = preg_split('#;\s*#', (string) $header_line);
                foreach ($key_value_pairs as $key_value) {
                    if (preg_match('#^(?P<headerKey>[^=]+)=\s*("?)(?P<headerValue>[^"]*)\2#', $key_value, $matches)) {
                        $header_key = $matches['headerKey'];
                        $header_value = $matches['headerValue'];
                    } else {
                        $header_key = $key_value;
                        $header_value = null;
                    }
                    // First K=V pair is always the cookie name and value
                    if ($header->get_name() === null) {
                        $header->set_name($header_key);
                        $header->set_value(urldecode($header_value ?? ''));
                        // set no encode value if raw and encoded values are the same
                        if (urldecode($header_value ?? '') === $header_value) {
                            $header->set_encode_value(false);
                        }
                        continue;
                    }
                    // Process the remaining elements
                    switch (str_replace(['-', '_'], '', strtolower($header_key))) {
                        case 'expires':
                            $header->set_expires($header_value);
                            break;
                        case 'domain':
                            $header->set_domain($header_value);
                            break;
                        case 'path':
                            $header->set_path($header_value);
                            break;
                        case 'secure':
                            $header->set_secure(true);
                            break;
                        case 'httponly':
                            $header->set_httponly(true);
                            break;
                        case 'version':
                            $header->set_version((int) $header_value);
                            break;
                        case 'maxage':
                            $header->set_max_age($header_value);
                            break;
                        case 'samesite':
                            $header->set_same_site($header_value);
                            break;
                        default:
                    }
                }
                return $header;
            };
        }
        [$name, $value] = Generic_Header::split_header_line($header_line);
        Header_Value::assert_valid($value);
        // some sites return set-cookie::value, this is to get rid of the second :
        $name = strtolower($name) === 'set-cookie:' ? 'set-cookie' : $name;
        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'set-cookie') {
            throw new Exception\InvalidArgumentException('Invalid header line for Set-Cookie string: "' . $name . '"');
        }
        $multiple_headers = preg_split('#(?<!Sun|Mon|Tue|Wed|Thu|Fri|Sat),\s*#', $value);
        if (count($multiple_headers) <= 1) {
            return $set_cookie_processor(array_pop($multiple_headers));
        }
        $headers = [];
        foreach ($multiple_headers as $header_line) {
            $headers[] = $set_cookie_processor($header_line);
        }
        return $headers;
    }
    /**
     * Cookie object constructor
     *
     * @todo Add validation of each one of the parameters (legal domain, etc.)
     * @param string|null              $name
     * @param string|null              $value
     * @param int|string|DateTime|null $expires
     * @param string|null              $path
     * @param string|null              $domain
     * @param bool                     $secure
     * @param bool                     $httponly
     * @param int|null                 $maxAge
     * @param int|null                 $version
     * @param string|null              $sameSite
     */
    public function __construct($name = null, $value = null, $expires = null, $path = null, $domain = null, $secure = false, $httponly = false, $max_age = null, $version = null, $same_site = null)
    {
        $this->type = 'Cookie';
        $this->set_name($name)->set_value($value)->set_version($version)->set_max_age($max_age)->set_domain($domain)->set_expires($expires)->set_path($path)->set_secure($secure)->set_http_only($httponly)->set_same_site($same_site);
    }
    /**
     * @return bool
     */
    public function get_encode_value()
    {
        return $this->encode_value;
    }
    /**
     * @param bool $encodeValue
     */
    public function set_encode_value($encode_value): void
    {
        $this->encode_value = (bool) $encode_value;
    }
    /**
     * @return string 'Set-Cookie'
     */
    public function get_field_name(): string
    {
        return 'Set-Cookie';
    }
    /**
     * @throws Exception\RuntimeException
     */
    public function get_field_value(): string
    {
        $name = $this->get_name();
        if ($name === '' || $name === null) {
            return '';
        }
        $value = $this->encode_value ? urlencode($this->get_value() ?? '') : $this->get_value();
        if ($this->has_quote_field_value()) {
            $value = '"' . $value . '"';
        }
        $field_value = $name . '=' . $value;
        $version = $this->get_version();
        if ($version !== null) {
            $field_value .= '; Version=' . $version;
        }
        $max_age = $this->get_max_age();
        if ($max_age !== null) {
            $field_value .= '; Max-Age=' . $max_age;
        }
        $expires = $this->get_expires();
        if ($expires) {
            $field_value .= '; Expires=' . $expires;
        }
        $domain = $this->get_domain();
        if ($domain) {
            $field_value .= '; Domain=' . $domain;
        }
        $path = $this->get_path();
        if ($path) {
            $field_value .= '; Path=' . $path;
        }
        if ($this->is_secure()) {
            $field_value .= '; Secure';
        }
        if ($this->is_httponly()) {
            $field_value .= '; HttpOnly';
        }
        $same_site = $this->get_same_site();
        if ($same_site !== null && array_key_exists(strtolower($same_site), self::SAME_SITE_ALLOWED_VALUES)) {
            $field_value .= '; SameSite=' . $same_site;
        }
        return $field_value;
    }
    /**
     * @param  string|null $name
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function set_name($name): static
    {
        Header_Value::assert_valid($name);
        $this->name = $name;
        return $this;
    }
    /**
     * @return string|null
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * @param  string|null $value
     * @return $this
     */
    public function set_value($value): static
    {
        $this->value = $value;
        return $this;
    }
    /**
     * @return string|null
     */
    public function get_value()
    {
        return $this->value;
    }
    /**
     * @param  int|null $version
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function set_version($version): static
    {
        if ($version !== null && !is_int($version)) {
            throw new Exception\InvalidArgumentException('Invalid Version number specified');
        }
        $this->version = $version;
        return $this;
    }
    /**
     * @return int|null
     */
    public function get_version()
    {
        return $this->version;
    }
    /**
     * @param  int $maxAge
     * @return $this
     */
    public function set_max_age($max_age): static
    {
        if ($max_age === null || !is_numeric($max_age)) {
            return $this;
        }
        $this->max_age = max(0, (int) $max_age);
        return $this;
    }
    /**
     * @return int|null
     */
    public function get_max_age()
    {
        return $this->max_age;
    }
    /**
     * @param  int|string|DateTime|null $expires
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function set_expires($expires): static
    {
        if ($expires === null) {
            $this->expires = null;
            return $this;
        }
        if ($expires instanceof DateTime) {
            $expires = $expires->format(DateTime::COOKIE);
        }
        $ts_expires = $expires;
        if (is_string($expires)) {
            $ts_expires = strtotime($expires);
            // if $tsExpires is invalid and PHP is compiled as 32bit. Check if it fail reason is the 2038 bug
            if (!is_int($ts_expires) && PHP_INT_SIZE === 4) {
                $date_time = new DateTime($expires);
                if ($date_time->format('Y') > 2038) {
                    $ts_expires = PHP_INT_MAX;
                }
            }
        }
        if (!is_int($ts_expires) || $ts_expires < 0) {
            throw new Exception\InvalidArgumentException('Invalid expires time specified');
        }
        $this->expires = $ts_expires;
        return $this;
    }
    /**
     * @param  bool $inSeconds
     * @return int|string|null
     */
    public function get_expires($in_seconds = false)
    {
        if ($this->expires === null) {
            return null;
        }
        if ($in_seconds) {
            return $this->expires;
        }
        return gmdate('D, d-M-Y H:i:s', $this->expires) . ' GMT';
    }
    /**
     * @param  string|null $domain
     * @return $this
     */
    public function set_domain($domain): static
    {
        Header_Value::assert_valid($domain);
        $this->domain = $domain;
        return $this;
    }
    /**
     * @return string|null
     */
    public function get_domain()
    {
        return $this->domain;
    }
    /**
     * @param  string|null $path
     * @return $this
     */
    public function set_path($path): static
    {
        Header_Value::assert_valid($path);
        $this->path = $path;
        return $this;
    }
    /**
     * @return string|null
     */
    public function get_path()
    {
        return $this->path;
    }
    /**
     * @param  bool|null $secure
     * @return $this
     */
    public function set_secure($secure): static
    {
        if (null !== $secure) {
            $secure = (bool) $secure;
        }
        $this->secure = $secure;
        return $this;
    }
    /**
     * Set whether the value for this cookie should be quoted
     *
     * @param  bool $quotedValue
     * @return $this
     */
    public function set_quote_field_value($quoted_value): static
    {
        $this->quote_field_value = (bool) $quoted_value;
        return $this;
    }
    /**
     * @return bool|null
     */
    public function is_secure()
    {
        return $this->secure;
    }
    /**
     * @param  bool|null $httponly
     * @return $this
     */
    public function set_httponly($httponly): static
    {
        if (null !== $httponly) {
            $httponly = (bool) $httponly;
        }
        $this->httponly = $httponly;
        return $this;
    }
    /**
     * @return bool|null
     */
    public function is_httponly()
    {
        return $this->httponly;
    }
    /**
     * Check whether the cookie has expired
     *
     * Always returns false if the cookie is a session cookie (has no expiry time)
     *
     * @param int|null $now Timestamp to consider as "now"
     */
    public function is_expired($now = null): bool
    {
        if ($now === null) {
            $now = time();
        }
        if (is_int($this->expires) && $this->expires < $now) {
            return true;
        }
        return false;
    }
    /**
     * Check whether the cookie is a session cookie (has no expiry time set)
     */
    public function is_session_cookie(): bool
    {
        return $this->expires === null;
    }
    /**
     * @return string|null
     */
    public function get_same_site()
    {
        return $this->same_site;
    }
    /**
     * @param  string|null $sameSite
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function set_same_site($same_site): static
    {
        if ($same_site === null) {
            $this->same_site = null;
            return $this;
        }
        if (!array_key_exists(strtolower($same_site), self::SAME_SITE_ALLOWED_VALUES)) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid value provided for SameSite directive: "%s"; expected one of: Strict, Lax or None', is_scalar($same_site) ? $same_site : gettype($same_site)));
        }
        $this->same_site = self::SAME_SITE_ALLOWED_VALUES[strtolower($same_site)];
        return $this;
    }
    /**
     * Check whether the value for this cookie should be quoted
     *
     * @return bool
     */
    public function has_quote_field_value()
    {
        return $this->quote_field_value;
    }
    /**
     * @param  string $requestDomain
     * @param  string $path
     * @param  bool   $isSecure
     */
    public function is_valid_for_request($request_domain, $path, $is_secure = false): bool
    {
        if ($this->get_domain() && strrpos($request_domain, $this->get_domain()) === false) {
            return false;
        }
        if ($this->get_path() && !str_starts_with($path, $this->get_path())) {
            return false;
        }
        if ($this->secure && $this->is_secure() !== $is_secure) {
            return false;
        }
        return true;
    }
    /**
     * Checks whether the cookie should be sent or not in a specific scenario
     *
     * @param string|Uri $uri URI to check against (secure, domain, path)
     * @param bool $matchSessionCookies Whether to send session cookies
     * @param int|null $now Override the current time when checking for expiry time
     * @throws Exception\InvalidArgumentException If URI does not have HTTP or HTTPS scheme.
     */
    public function match($uri, $match_session_cookies = true, $now = null): bool
    {
        if (is_string($uri)) {
            $uri = Uri_Factory::factory($uri);
        }
        // Make sure we have a valid Laminas_Uri_Http object
        if (!($uri->is_valid() && ($uri->get_scheme() === 'http' || $uri->get_scheme() === 'https'))) {
            throw new Exception\InvalidArgumentException('Passed URI is not a valid HTTP or HTTPS URI');
        }
        // Check that the cookie is secure (if required) and not expired
        if ($this->secure && $uri->get_scheme() !== 'https') {
            return false;
        }
        if ($this->is_expired($now)) {
            return false;
        }
        if ($this->is_session_cookie() && !$match_session_cookies) {
            return false;
        }
        // Check if the domain matches
        if (!self::match_cookie_domain($this->get_domain(), $uri->get_host())) {
            return false;
        }
        // Check that path matches using prefix match
        if (!self::match_cookie_path($this->get_path(), $uri->get_path())) {
            return false;
        }
        // If we didn't die until now, return true.
        return true;
    }
    /**
     * Check if a cookie's domain matches a host name.
     *
     * Used by Laminas\Http\Cookies for cookie matching
     *
     * @param  string $cookieDomain
     * @param  string $host
     */
    public static function match_cookie_domain($cookie_domain, $host): bool
    {
        $cookie_domain = strtolower($cookie_domain);
        $host = strtolower($host);
        // Check for either exact match or suffix match
        return $cookie_domain === $host || preg_match('/' . preg_quote($cookie_domain) . '$/', $host);
    }
    /**
     * Check if a cookie's path matches a URL path
     *
     * Used by Laminas\Http\Cookies for cookie matching
     *
     * @param  string $cookiePath
     * @param  string $path
     */
    public static function match_cookie_path($cookie_path, $path): bool
    {
        return str_starts_with($path, $cookie_path);
    }
    public function to_string(): string
    {
        return 'Set-Cookie: ' . $this->get_field_value();
    }
    /**
     * @return string
     * @throws Exception\RuntimeException
     */
    public function to_string_multiple_headers(array $headers)
    {
        $header_line = $this->to_string();
        /** @var SetCookie $header */
        foreach ($headers as $header) {
            if (!$header instanceof Set_Cookie) {
                throw new Exception\RuntimeException('The SetCookie multiple header implementation can only accept an array of SetCookie headers');
            }
            $header_line .= "\n" . $header->to_string();
        }
        return $header_line;
    }
}