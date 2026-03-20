<?php

declare (strict_types=1);
namespace Laminas\Http;

use function array_keys;
use function array_merge;
use ArrayIterator;
use function count;
use function is_array;
use function is_string;
use Laminas\Http\Header\Set_Cookie;
use Laminas\Uri;
use function sprintf;
use function strrpos;
use function substr;
/**
 * A Laminas\Http\Cookies object is designed to contain and maintain HTTP cookies, and should
 * be used along with Laminas\Http\Client in order to manage cookies across HTTP requests and
 * responses.
 *
 * The class contains an array of Laminas\Http\Header\Cookie objects. Cookies can be added
 * automatically from a request or manually. Then, the Cookies class can find and return the
 * cookies needed for a specific HTTP request.
 *
 * A special parameter can be passed to all methods of this class that return cookies: Cookies
 * can be returned either in their native form (as Laminas\Http\Header\Cookie objects) or as strings -
 * the later is suitable for sending as the value of the "Cookie" header in an HTTP request.
 * You can also choose, when returning more than one cookie, whether to get an array of strings
 * (by passing Laminas\Http\Client\Cookies::COOKIE_STRING_ARRAY) or one unified string for all cookies
 * (by passing Laminas\Http\Client\Cookies::COOKIE_STRING_CONCAT).
 *
 * @link       http://wp.netscape.com/newsref/std/cookie_spec.html for some specs.
 */
class Cookies extends Headers
{
    /**
     * Return cookie(s) as a Laminas\Http\Cookie object
     */
    public const COOKIE_OBJECT = 0;
    /**
     * Return cookie(s) as a string (suitable for sending in an HTTP request)
     */
    public const COOKIE_STRING_ARRAY = 1;
    /**
     * Return all cookies as one long string (suitable for sending in an HTTP request)
     */
    public const COOKIE_STRING_CONCAT = 2;
    /**
     * Return all cookies as one long string (strict mode)
     *  - Single space after the semi-colon separating each cookie
     *  - Remove trailing semi-colon, if any
     */
    public const COOKIE_STRING_CONCAT_STRICT = 3;
    /** @var array */
    protected $cookies = [];
    /** @var Headers */
    protected $headers;
    /** @var array */
    protected $raw_cookies;
    /**
     * @static
     * @throws Exception\RuntimeException
     * @param string $string
     */
    public static function from_string($string): never
    {
        throw new Exception\RuntimeException(self::class . '::' . __FUNCTION__ . ' should not be used as a factory, use ' . __NAMESPACE__ . '\Headers::fromString() instead.');
    }
    /**
     * Add a cookie to the class. Cookie should be passed either as a Laminas\Http\Header\SetCookie object
     * or as a string - in which case an object is created from the string.
     *
     * @param SetCookie|string $cookie
     * @param Uri\Uri|string $refUri Optional reference URI (for domain, path, secure)
     * @throws Exception\InvalidArgumentException
     */
    public function add_cookie($cookie, $ref_uri = null): void
    {
        if (is_string($cookie)) {
            $cookie = Set_Cookie::from_string($cookie, $ref_uri);
        }
        if ($cookie instanceof Set_Cookie) {
            $domain = $cookie->get_domain();
            $path = $cookie->get_path();
            if (!isset($this->cookies[$domain])) {
                $this->cookies[$domain] = [];
            }
            if (!isset($this->cookies[$domain][$path])) {
                $this->cookies[$domain][$path] = [];
            }
            $this->cookies[$domain][$path][$cookie->get_name()] = $cookie;
            $this->raw_cookies[] = $cookie;
        } else {
            throw new Exception\InvalidArgumentException('Supplient argument is not a valid cookie string or object');
        }
    }
    /**
     * Parse an HTTP response, adding all the cookies set in that response
     *
     * @param Uri\Uri|string $refUri Requested URI
     */
    public function add_cookies_from_response(Response $response, $ref_uri): void
    {
        $cookie_hdrs = $response->get_headers()->get('Set-Cookie');
        if (is_array($cookie_hdrs) || $cookie_hdrs instanceof ArrayIterator) {
            foreach ($cookie_hdrs as $cookie) {
                $this->add_cookie($cookie, $ref_uri);
            }
        } elseif (is_string($cookie_hdrs)) {
            $this->add_cookie($cookie_hdrs, $ref_uri);
        }
    }
    /**
     * Get all cookies in the cookie jar as an array
     *
     * @param int $retAs Whether to return cookies as objects of \Laminas\Http\Header\SetCookie or as strings
     * @return array|string
     */
    public function get_all_cookies($ret_as = self::COOKIE_OBJECT)
    {
        return $this->_flatten_cookies_array($this->cookies, $ret_as);
    }
    /**
     * Return an array of all cookies matching a specific request according to the request URI,
     * whether session cookies should be sent or not, and the time to consider as "now" when
     * checking cookie expiry time.
     *
     * @param string|Uri\Uri $uri URI to check against (secure, domain, path)
     * @param bool $matchSessionCookies Whether to send session cookies
     * @param int $retAs Whether to return cookies as objects of \Laminas\Http\Header\Cookie or as strings
     * @param int $now Override the current time when checking for expiry time
     * @throws Exception\InvalidArgumentException If invalid URI specified.
     * @return array|string
     */
    public function get_matching_cookies($uri, $match_session_cookies = true, $ret_as = self::COOKIE_OBJECT, $now = null)
    {
        if (is_string($uri)) {
            $uri = Uri\Uri_Factory::factory($uri, 'http');
        } elseif (!$uri instanceof Uri\Uri) {
            throw new Exception\InvalidArgumentException('Invalid URI string or object passed');
        }
        $host = $uri->get_host();
        if (empty($host)) {
            throw new Exception\InvalidArgumentException('Invalid URI specified; does not contain a host');
        }
        // First, reduce the array of cookies to only those matching domain and path
        $cookies = $this->_match_domain($host);
        $cookies = $this->_match_path($cookies, $uri->get_path());
        $cookies = $this->_flatten_cookies_array($cookies, self::COOKIE_OBJECT);
        // Next, run Cookie->match on all cookies to check secure, time and session matching
        $ret = [];
        foreach ($cookies as $cookie) {
            if ($cookie->match($uri, $match_session_cookies, $now)) {
                $ret[] = $cookie;
            }
        }
        // Now, use self::_flattenCookiesArray again - only to convert to the return format ;)
        $ret = $this->_flatten_cookies_array($ret, $ret_as);
        return $ret;
    }
    /**
     * Get a specific cookie according to a URI and name
     *
     * @param Uri\Uri|string $uri The uri (domain and path) to match
     * @param string $cookieName The cookie's name
     * @param int $retAs Whether to return cookies as objects of \Laminas\Http\Header\SetCookie or as strings
     * @throws Exception\InvalidArgumentException If invalid URI specified or invalid $retAs value.
     * @return SetCookie|string
     */
    public function get_cookie($uri, $cookie_name, $ret_as = self::COOKIE_OBJECT)
    {
        if (is_string($uri)) {
            $uri = Uri\Uri_Factory::factory($uri, 'http');
        } elseif (!$uri instanceof Uri\Uri) {
            throw new Exception\InvalidArgumentException('Invalid URI specified');
        }
        $host = $uri->get_host();
        if (empty($host)) {
            throw new Exception\InvalidArgumentException('Invalid URI specified; host missing');
        }
        // Get correct cookie path
        $path = $uri->get_path() ?? '';
        $last_slash_pos = strrpos($path, '/') ?: 0;
        $path = substr($path, 0, $last_slash_pos);
        if (!$path) {
            $path = '/';
        }
        if (isset($this->cookies[$uri->get_host()][$path][$cookie_name])) {
            $cookie = $this->cookies[$uri->get_host()][$path][$cookie_name];
            return match ($ret_as) {
                self::COOKIE_OBJECT => $cookie,
                self::COOKIE_STRING_ARRAY, self::COOKIE_STRING_CONCAT => $cookie->__toString(),
                default => throw new Exception\InvalidArgumentException(sprintf('Invalid value passed for $retAs: %s', $ret_as)),
            };
        }
        return false;
    }
    /**
     * Helper function to recursively flatten an array. Should be used when exporting the
     * cookies array (or parts of it)
     *
     * @param SetCookie|array $ptr
     * @param int $retAs What value to return
     * @return array|string
     */
    // @codingStandardsIgnoreStart
    protected function _flatten_cookies_array($ptr, $ret_as = self::COOKIE_OBJECT)
    {
        // @codingStandardsIgnoreEnd
        if (is_array($ptr)) {
            $ret = $ret_as === self::COOKIE_STRING_CONCAT ? '' : [];
            foreach ($ptr as $item) {
                if ($ret_as === self::COOKIE_STRING_CONCAT) {
                    $ret .= $this->_flatten_cookies_array($item, $ret_as);
                } else {
                    $ret = array_merge($ret, $this->_flatten_cookies_array($item, $ret_as));
                }
            }
            return $ret;
        }
        // @codingStandardsIgnoreEnd
        if ($ptr instanceof Set_Cookie) {
            return match ($ret_as) {
                self::COOKIE_STRING_ARRAY => [$ptr->__toString()],
                self::COOKIE_STRING_CONCAT => $ptr->__toString(),
                default => [$ptr],
            };
        }
    }
    /**
     * Return a subset of the cookies array matching a specific domain
     *
     * @param string $domain
     */
    // @codingStandardsIgnoreStart
    protected function _match_domain($domain): array
    {
        // @codingStandardsIgnoreEnd
        $ret = [];
        foreach (array_keys($this->cookies) as $cdom) {
            if (Set_Cookie::match_cookie_domain($cdom, $domain)) {
                $ret[$cdom] = $this->cookies[$cdom];
            }
        }
        return $ret;
    }
    /**
     * Return a subset of a domain-matching cookies that also match a specified path
     *
     * @param array $domains
     * @param string $path
     */
    // @codingStandardsIgnoreStart
    protected function _match_path($domains, $path): array
    {
        // @codingStandardsIgnoreEnd
        $ret = [];
        foreach ($domains as $dom => $paths_array) {
            foreach (array_keys($paths_array) as $cpath) {
                if (Set_Cookie::match_cookie_path($cpath, $path)) {
                    if (!isset($ret[$dom])) {
                        $ret[$dom] = [];
                    }
                    $ret[$dom][$cpath] = $paths_array[$cpath];
                }
            }
        }
        return $ret;
    }
    /**
     * Create a new Cookies object and automatically load into it all the
     * cookies set in a Response object. If $uri is set, it will be
     * considered as the requested URI for setting default domain and path
     * of the cookie.
     *
     * @param Response $response HTTP Response object
     * @param Uri\Uri|string $refUri The requested URI
     * @todo Add the $uri functionality.
     */
    public static function from_response(Response $response, $ref_uri): static
    {
        $jar = new static();
        $jar->add_cookies_from_response($response, $ref_uri);
        return $jar;
    }
    /**
     * Tells if the array of cookies is empty
     */
    public function is_empty(): bool
    {
        return count($this) === 0;
    }
    /**
     * Empties the cookieJar of any cookie
     *
     * @return $this
     */
    public function reset(): static
    {
        $this->cookies = $this->raw_cookies = [];
        return $this;
    }
}