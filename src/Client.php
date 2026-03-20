<?php

declare (strict_types=1);
namespace Laminas\Http;

use function array_merge;
use ArrayIterator;
use function base64_encode;
use function basename;
use function class_exists;
use const CURLAUTH_DIGEST;
use const CURLOPT_HTTPAUTH;
use const CURLOPT_USERPWD;
use function defined;
use function explode;
use function fclose;
use function file_get_contents;
use const FILEINFO_MIME;
use function finfo_file;
use function finfo_open;
use function fopen;
use function fstat;
use function func_get_arg;
use function func_num_args;
use function function_exists;
use function http_build_query;
use function in_array;
use function ini_get;
use function is_array;
use function is_int;
use function is_resource;
use function is_string;
use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\Client\Adapter\Socket;
use Laminas\Http\Client\Exception\RuntimeException;
use Laminas\Http\Header\Set_Cookie;
use Laminas\Stdlib\Array_Utils;
use Laminas\Stdlib\Dispatchable_Interface;
use Laminas\Stdlib\Error_Handler;
use Laminas\Stdlib\Request_Interface;
use Laminas\Stdlib\Response_Interface;
use Laminas\Uri\Http;
use function md5;
use function microtime;
use function mime_content_type;
use function preg_match;
use function preg_quote;
use function rewind;
use function rtrim;
use function sprintf;
use function str_replace;
use function stream_get_meta_data;
use function stripos;
use function strlen;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use Traversable;
use function trim;
/**
 * Http client
 */
class Client implements Dispatchable_Interface
{
    /**
     * @const string Supported HTTP Authentication methods
     */
    public const AUTH_BASIC = 'basic';
    public const AUTH_DIGEST = 'digest';
    /**
     * @const string POST data encoding methods
     */
    public const ENC_URLENCODED = 'application/x-www-form-urlencoded';
    public const ENC_FORMDATA = 'multipart/form-data';
    /**
     * @const string DIGEST Authentication
     */
    public const DIGEST_REALM = 'realm';
    public const DIGEST_QOP = 'qop';
    public const DIGEST_NONCE = 'nonce';
    public const DIGEST_OPAQUE = 'opaque';
    public const DIGEST_NC = 'nc';
    public const DIGEST_CNONCE = 'cnonce';
    /** @var Response */
    protected $response;
    /** @var Request */
    protected $request;
    /** @var Client\Adapter\AdapterInterface */
    protected $adapter;
    /** @var array */
    protected $auth = [];
    /** @var string */
    protected $stream_name;
    /** @var resource|null */
    protected $stream_handle;
    /** @var array of Header\SetCookie */
    protected $cookies = [];
    /** @var string */
    protected $enc_type = '';
    /** @var Request */
    protected $last_raw_request;
    /** @var Response */
    protected $last_raw_response;
    /** @var int */
    protected $redirect_counter = 0;
    /**
     * Configuration array, set using the constructor or using ::setOptions()
     *
     * @var array
     */
    protected $config = ['maxredirects' => 5, 'strictredirects' => false, 'useragent' => 'Laminas_Http_Client', 'timeout' => 10, 'connecttimeout' => null, 'adapter' => Socket::class, 'httpversion' => Request::VERSION_11, 'storeresponse' => true, 'keepalive' => false, 'outputstream' => false, 'encodecookies' => true, 'argseparator' => null, 'rfc3986strict' => false, 'sslcafile' => null, 'sslcapath' => null];
    /**
     * Fileinfo magic database resource
     *
     * This variable is populated the first time _detectFileMimeType is called
     * and is then reused on every call to this method
     *
     * @var resource
     */
    protected static $file_info_db;
    /**
     * Constructor
     *
     * @param string $uri
     * @param array|Traversable $options
     */
    public function __construct($uri = null, $options = null)
    {
        if ($uri !== null) {
            $this->set_uri($uri);
        }
        if ($options !== null) {
            $this->set_options($options);
        }
    }
    /**
     * Set configuration parameters for this HTTP client
     *
     * @param  array|Traversable $options
     * @return $this
     * @throws Client\Exception\InvalidArgumentException
     */
    public function set_options($options = []): static
    {
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        if (!is_array($options)) {
            throw new Client\Exception\InvalidArgumentException('Config parameter is not valid');
        }
        /** Config Key Normalization */
        foreach ($options as $k => $v) {
            $this->config[str_replace(['-', '_', ' ', '.'], '', strtolower((string) $k))] = $v;
            // replace w/ normalized
        }
        // Pass configuration options to the adapter if it exists
        if ($this->adapter instanceof Client\Adapter\Adapter_Interface) {
            $this->adapter->set_options($options);
        }
        return $this;
    }
    /**
     * Load the connection adapter
     *
     * While this method is not called more than one for a client, it is
     * separated from ->request() to preserve logic and readability
     *
     * @param  Client\Adapter\AdapterInterface|string $adapter
     * @return $this
     * @throws Client\Exception\InvalidArgumentException
     */
    public function set_adapter($adapter): static
    {
        if (is_string($adapter)) {
            if (!class_exists($adapter)) {
                throw new Client\Exception\InvalidArgumentException('Unable to locate adapter class "' . $adapter . '"');
            }
            $adapter = new $adapter();
        }
        if (!$adapter instanceof Client\Adapter\Adapter_Interface) {
            throw new Client\Exception\InvalidArgumentException('Passed adapter is not a HTTP connection adapter');
        }
        $this->adapter = $adapter;
        $config = $this->config;
        unset($config['adapter']);
        $this->adapter->set_options($config);
        return $this;
    }
    /**
     * Load the connection adapter
     *
     * @return Client\Adapter\AdapterInterface
     */
    public function get_adapter()
    {
        if (!$this->adapter) {
            $this->set_adapter($this->config['adapter']);
        }
        return $this->adapter;
    }
    /**
     * Set request
     *
     * @return $this
     */
    public function set_request(Request $request): static
    {
        $this->request = $request;
        return $this;
    }
    /**
     * Get Request
     *
     * @return Request
     */
    public function get_request()
    {
        if (empty($this->request)) {
            $this->request = new Request();
            $this->request->set_allow_custom_methods(false);
        }
        return $this->request;
    }
    /**
     * Set response
     *
     * @return $this
     */
    public function set_response(Response $response): static
    {
        $this->response = $response;
        return $this;
    }
    /**
     * Get Response
     *
     * @return Response
     */
    public function get_response()
    {
        if (empty($this->response)) {
            $this->response = new Response();
        }
        return $this->response;
    }
    /**
     * Get the last request (as a string)
     *
     * @return string
     */
    public function get_last_raw_request()
    {
        return $this->last_raw_request;
    }
    /**
     * Get the last response (as a string)
     *
     * @return string
     */
    public function get_last_raw_response()
    {
        return $this->last_raw_response;
    }
    /**
     * Get the redirections count
     *
     * @return int
     */
    public function get_redirections_count()
    {
        return $this->redirect_counter;
    }
    /**
     * Set Uri (to the request)
     *
     * @param string|Http $uri
     * @return $this
     */
    public function set_uri($uri): static
    {
        if (!empty($uri)) {
            // remember host of last request
            $last_host = $this->get_request()->get_uri()->get_host();
            $this->get_request()->set_uri($uri);
            // if host changed, the HTTP authentication should be cleared for security
            // reasons, see #4215 for a discussion - currently authentication is also
            // cleared for peer subdomains due to technical limits
            $next_host = $this->get_request()->get_uri()->get_host();
            if (!empty($last_host) && !preg_match('/' . preg_quote((string) $last_host, '/') . '$/i', (string) $next_host)) {
                $this->clear_auth();
            }
            $uri = $this->get_uri();
            $user = $uri->get_user();
            $password = $uri->get_password();
            // Set auth if username and password has been specified in the uri
            if ($user && $password) {
                $this->set_auth($user, $password);
            }
            // We have no ports, set the defaults
            if (!$uri->get_port() && $uri->is_absolute()) {
                $uri->set_port($uri->get_scheme() === 'https' ? 443 : 80);
            }
        }
        return $this;
    }
    /**
     * Get uri (from the request)
     *
     * @return Http
     */
    public function get_uri()
    {
        return $this->get_request()->get_uri();
    }
    /**
     * Set the HTTP method (to the request)
     *
     * @param string $method
     * @return $this
     */
    public function set_method($method): static
    {
        $method = $this->get_request()->set_method($method)->get_method();
        if (empty($this->enc_type) && in_array($method, [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_DELETE, Request::METHOD_PATCH, Request::METHOD_OPTIONS], true)) {
            $this->set_enc_type(self::ENC_URLENCODED);
        }
        return $this;
    }
    /**
     * Get the HTTP method
     *
     * @return string
     */
    public function get_method()
    {
        return $this->get_request()->get_method();
    }
    /**
     * Set the query string argument separator
     *
     * @param string $argSeparator
     * @return $this
     */
    public function set_arg_separator($arg_separator): static
    {
        $this->set_options(['argseparator' => $arg_separator]);
        return $this;
    }
    /**
     * Get the query string argument separator
     *
     * @return string
     */
    public function get_arg_separator()
    {
        $arg_separator = $this->config['argseparator'];
        if (empty($arg_separator)) {
            $arg_separator = ini_get('arg_separator.output');
            $this->set_arg_separator($arg_separator);
        }
        return $arg_separator;
    }
    /**
     * Set the encoding type and the boundary (if any)
     *
     * @param string $encType
     * @param string $boundary
     * @return $this
     */
    public function set_enc_type(?string $enc_type, $boundary = null): static
    {
        if (null === $enc_type || empty($enc_type)) {
            $this->enc_type = null;
            return $this;
        }
        if (!empty($boundary)) {
            $enc_type .= sprintf('; boundary=%s', $boundary);
        }
        $this->enc_type = $enc_type;
        return $this;
    }
    /**
     * Get the encoding type
     *
     * @return string
     */
    public function get_enc_type()
    {
        return $this->enc_type;
    }
    /**
     * Set raw body (for advanced use cases)
     *
     * @param string $body
     * @return $this
     */
    public function set_raw_body($body): static
    {
        $this->get_request()->set_content($body);
        return $this;
    }
    /**
     * Set the POST parameters
     *
     * @return $this
     */
    public function set_parameter_post(array $post): static
    {
        $this->get_request()->get_post()->from_array($post);
        return $this;
    }
    /**
     * Set the GET parameters
     *
     * @return $this
     */
    public function set_parameter_get(array $query): static
    {
        $this->get_request()->get_query()->from_array($query);
        return $this;
    }
    /**
     * Reset all the HTTP parameters (request, response, etc)
     *
     * @param  bool   $clearCookies  Also clear all valid cookies? (defaults to false)
     * @return $this
     */
    public function reset_parameters($clear_cookies = false): static
    {
        $clear_auth = true;
        if (func_num_args() > 1) {
            $clear_auth = func_get_arg(1);
        }
        $uri = $this->get_uri();
        $this->stream_name = null;
        $this->enc_type = null;
        $this->request = null;
        $this->response = null;
        $this->last_raw_request = null;
        $this->last_raw_response = null;
        $this->set_uri($uri);
        if ($clear_cookies) {
            $this->clear_cookies();
        }
        if ($clear_auth) {
            $this->clear_auth();
        }
        return $this;
    }
    /**
     * Return the current cookies
     *
     * @return array
     */
    public function get_cookies()
    {
        return $this->cookies;
    }
    /**
     * Get the cookie Id (name+domain+path)
     *
     * @param  SetCookie|Header\Cookie $cookie
     */
    protected function get_cookie_id($cookie): string|false
    {
        if ($cookie instanceof Header\Set_Cookie || $cookie instanceof Header\Cookie) {
            return $cookie->get_name() . $cookie->get_domain() . $cookie->get_path();
        }
        return false;
    }
    /**
     * Add a cookie
     *
     * @param array|ArrayIterator|SetCookie|string $cookie
     * @param string  $value
     * @param string  $expire
     * @param string  $path
     * @param string  $domain
     * @param  bool $secure
     * @param  bool $httponly
     * @param string  $maxAge
     * @param string  $version
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function add_cookie($cookie, $value = null, $expire = null, $path = null, $domain = null, $secure = false, $httponly = true, $max_age = null, $version = null): static
    {
        if (is_array($cookie) || $cookie instanceof ArrayIterator) {
            foreach ($cookie as $set_cookie) {
                if ($set_cookie instanceof Header\Set_Cookie) {
                    $this->cookies[$this->get_cookie_id($set_cookie)] = $set_cookie;
                } else {
                    throw new Exception\InvalidArgumentException('The cookie parameter is not a valid Set-Cookie type');
                }
            }
        } elseif (is_string($cookie) && $value !== null) {
            $set_cookie = new Set_Cookie($cookie, $value, $expire, $path, $domain, $secure, $httponly, $max_age, $version);
            $this->cookies[$this->get_cookie_id($set_cookie)] = $set_cookie;
        } elseif ($cookie instanceof Header\Set_Cookie) {
            $this->cookies[$this->get_cookie_id($cookie)] = $cookie;
        } else {
            throw new Exception\InvalidArgumentException('Invalid parameter type passed as Cookie');
        }
        return $this;
    }
    /**
     * Set an array of cookies
     *
     * @param  array|SetCookie[] $cookies Cookies as name=>value pairs or instances of SetCookie.
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function set_cookies($cookies): static
    {
        if (is_array($cookies)) {
            $this->clear_cookies();
            foreach ($cookies as $name => $value) {
                if ($value instanceof Set_Cookie) {
                    $this->add_cookie($value);
                } else {
                    $this->add_cookie($name, $value);
                }
            }
        } else {
            throw new Exception\InvalidArgumentException('Invalid cookies passed as parameter, it must be an array');
        }
        return $this;
    }
    /**
     * Clear all the cookies
     */
    public function clear_cookies(): void
    {
        $this->cookies = [];
    }
    /**
     * Set the headers (for the request)
     *
     * @param  Headers|array $headers
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function set_headers($headers): static
    {
        if (is_array($headers)) {
            $new_headers = new Headers();
            $new_headers->add_headers($headers);
            $this->get_request()->set_headers($new_headers);
        } elseif ($headers instanceof Headers) {
            $this->get_request()->set_headers($headers);
        } else {
            throw new Exception\InvalidArgumentException('Invalid parameter headers passed');
        }
        return $this;
    }
    /**
     * Check if exists the header type specified
     *
     * @param  string $name
     * @return bool
     */
    public function has_header($name)
    {
        $headers = $this->get_request()->get_headers();
        if ($headers instanceof Headers) {
            return $headers->has($name);
        }
        return false;
    }
    /**
     * Get the header value of the request
     *
     * @param  string $name
     * @return string|bool
     */
    public function get_header($name)
    {
        $headers = $this->get_request()->get_headers();
        if (!$headers instanceof Headers) {
            return false;
        }
        if ($headers->get($name)) {
            return $headers->get($name)->get_field_value();
        }
        return false;
    }
    /**
     * Set streaming for received data
     *
     * @param string|bool $streamfile Stream file, true for temp file, false/null for no streaming
     * @return $this
     */
    public function set_stream($streamfile = true): static
    {
        $this->set_options(['outputstream' => $streamfile]);
        return $this;
    }
    /**
     * Get status of streaming for received data
     *
     * @return bool|string
     */
    public function get_stream()
    {
        if (null !== $this->stream_name) {
            return $this->stream_name;
        }
        return $this->config['outputstream'];
    }
    /**
     * Create temporary stream
     *
     * @return resource
     * @throws Exception\RuntimeException
     */
    protected function open_temp_stream()
    {
        $this->stream_name = $this->config['outputstream'];
        if (!is_string($this->stream_name)) {
            // If name is not given, create temp name
            $this->stream_name = tempnam($this->config['streamtmpdir'] ?? sys_get_temp_dir(), self::class);
        }
        Error_Handler::start();
        $fp = fopen($this->stream_name, 'w+b');
        $error = Error_Handler::stop();
        if (false === $fp) {
            if ($this->adapter instanceof Client\Adapter\Adapter_Interface) {
                $this->adapter->close();
            }
            throw new Exception\RuntimeException(sprintf('Could not open temp file %s', $this->stream_name), 0, $error);
        }
        return $fp;
    }
    /**
     * Create a HTTP authentication "Authorization:" header according to the
     * specified user, password and authentication method.
     *
     * @param string $user
     * @param string $password
     * @param string $type
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function set_auth($user, $password, $type = self::AUTH_BASIC): static
    {
        if (!defined('static::AUTH_' . strtoupper($type))) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid or not supported authentication type: \'%s\'', $type));
        }
        if (empty($user)) {
            throw new Exception\InvalidArgumentException('The username cannot be empty');
        }
        $this->auth = ['user' => $user, 'password' => $password, 'type' => $type];
        return $this;
    }
    /**
     * Clear http authentication
     */
    public function clear_auth(): void
    {
        $this->auth = [];
    }
    /**
     * Calculate the response value according to the HTTP authentication type
     *
     * @see http://www.faqs.org/rfcs/rfc2617.html
     *
     * @param string $type
     * @param array $digest
     * @param null|string $entityBody
     * @throws Exception\InvalidArgumentException
     */
    protected function calc_auth_digest(string $user, string $password, $type = self::AUTH_BASIC, $digest = [], $entity_body = null): string|false
    {
        if (!defined('self::AUTH_' . strtoupper($type))) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid or not supported authentication type: \'%s\'', $type));
        }
        $response = false;
        switch (strtolower($type)) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (str_contains($user, ':')) {
                    throw new Exception\InvalidArgumentException('The user name cannot contain \':\' in Basic HTTP authentication');
                }
                $response = base64_encode($user . ':' . $password);
                break;
            case self::AUTH_DIGEST:
                if (empty($digest)) {
                    throw new Exception\InvalidArgumentException('The digest cannot be empty');
                }
                foreach ($digest as $key => $value) {
                    if (!defined('self::DIGEST_' . strtoupper((string) $key))) {
                        throw new Exception\InvalidArgumentException(sprintf('Invalid or not supported digest authentication parameter: \'%s\'', $key));
                    }
                }
                $ha1 = md5($user . ':' . $digest['realm'] . ':' . $password);
                if (empty($digest['qop']) || strtolower((string) $digest['qop']) === 'auth') {
                    $ha2 = md5($this->get_method() . ':' . $this->get_uri()->get_path());
                } elseif (strtolower((string) $digest['qop']) === 'auth-int') {
                    if (empty($entity_body)) {
                        throw new Exception\InvalidArgumentException('I cannot use the auth-int digest authentication without the entity body');
                    }
                    $ha2 = md5($this->get_method() . ':' . $this->get_uri()->get_path() . ':' . md5($entity_body));
                }
                if (empty($digest['qop'])) {
                    $response = md5($ha1 . ':' . $digest['nonce'] . ':' . $ha2);
                } else {
                    $response = md5($ha1 . ':' . $digest['nonce'] . ':' . $digest['nc'] . ':' . $digest['cnonce'] . ':' . $digest['qop'] . ':' . $ha2);
                }
                break;
        }
        return $response;
    }
    /**
     * Dispatch
     *
     * @return ResponseInterface
     */
    public function dispatch(Request_Interface $request, ?Response_Interface $response = null)
    {
        return $this->send($request);
    }
    /**
     * Send HTTP request
     *
     * @return Response
     * @throws Exception\RuntimeException
     * @throws RuntimeException
     */
    public function send(?Request $request = null)
    {
        if ($request !== null) {
            $this->set_request($request);
        }
        $this->redirect_counter = 0;
        $adapter = $this->get_adapter();
        // Send the first request. If redirected, continue.
        do {
            // uri
            $uri = $this->get_uri();
            // query
            $query = $this->get_request()->get_query();
            if (!empty($query)) {
                $query_array = $query->to_array();
                if (!empty($query_array)) {
                    $new_uri = $uri->to_string();
                    $query_string = http_build_query($query_array, '', $this->get_arg_separator());
                    if ($this->config['rfc3986strict']) {
                        $query_string = str_replace('+', '%20', $query_string);
                    }
                    if (str_contains((string) $new_uri, '?')) {
                        $new_uri .= $this->get_arg_separator() . $query_string;
                    } else {
                        $new_uri .= '?' . $query_string;
                    }
                    $uri = new Http($new_uri);
                }
            }
            // If we have no ports, set the defaults
            if (!$uri->get_port() && $uri->is_absolute()) {
                $uri->set_port($uri->get_scheme() === 'https' ? 443 : 80);
            }
            // method
            $method = $this->get_request()->get_method();
            // this is so the correct Encoding Type is set
            $this->set_method($method);
            // body
            $body = $this->prepare_body();
            // headers
            $headers = $this->prepare_headers($body, $uri);
            $secure = $uri->get_scheme() === 'https';
            // cookies
            $cookie = $this->prepare_cookies($uri->get_host(), $uri->get_path(), $secure);
            if ($cookie->get_field_value()) {
                $headers['Cookie'] = $cookie->get_field_value();
            }
            // check that adapter supports streaming before using it
            if (is_resource($body) && !$adapter instanceof Client\Adapter\Stream_Interface) {
                throw new RuntimeException('Adapter does not support streaming');
            }
            $this->stream_handle = null;
            // calling protected method to allow extending classes
            // to wrap the interaction with the adapter
            $response = $this->do_request($uri, $method, $secure, $headers, $body);
            $stream = $this->stream_handle;
            $this->stream_handle = null;
            if (!$response) {
                if ($stream !== null) {
                    fclose($stream);
                }
                throw new Exception\RuntimeException('Unable to read response, or response is empty');
            }
            if ($this->config['storeresponse']) {
                $this->last_raw_response = $response;
            } else {
                $this->last_raw_response = null;
            }
            if ($this->config['outputstream']) {
                $stream = $this->get_stream();
                if (is_string($stream)) {
                    $stream = fopen($stream, 'r');
                }
                $stream_meta_data = stream_get_meta_data($stream);
                if ($stream_meta_data['seekable']) {
                    rewind($stream);
                }
                // cleanup the adapter
                $adapter->set_output_stream(null);
                $response = Response\Stream::from_stream($response, $stream);
                $response->set_stream_name($this->stream_name);
                if (!is_string($this->config['outputstream'])) {
                    // we used temp name, will need to clean up
                    $response->set_cleanup(true);
                }
            } else {
                $response = $this->get_response()->from_string($response);
            }
            // Get the cookies from response (if any)
            $set_cookies = $response->get_cookie();
            if (!empty($set_cookies)) {
                $this->add_cookie($set_cookies);
            }
            // If we got redirected, look for the Location header
            if ($response->is_redirect() && $response->get_headers()->has('Location')) {
                // Avoid problems with buggy servers that add whitespace at the
                // end of some headers
                $location = trim($response->get_headers()->get('Location')->get_field_value());
                // Check whether we send the exact same request again, or drop the parameters
                // and send a GET request
                if ($response->get_status_code() === 303 || !$this->config['strictredirects'] && ($response->get_status_code() === 302 || $response->get_status_code() === 301)) {
                    $this->reset_parameters(false, false);
                    $this->set_method(Request::METHOD_GET);
                }
                // If we got a well formed absolute URI
                if (($scheme = substr($location, 0, 6)) && ($scheme === 'http:/' || $scheme === 'https:')) {
                    // setURI() clears parameters if host changed, see #4215
                    $this->set_uri($location);
                } else {
                    // Split into path and query and set the query
                    if (str_contains($location, '?')) {
                        [$location, $query] = explode('?', $location, 2);
                    } else {
                        $query = '';
                    }
                    $this->get_uri()->set_query($query);
                    // Else, if we got just an absolute path, set it
                    if (str_starts_with($location, '/')) {
                        $this->get_uri()->set_path($location);
                        // Else, assume we have a relative path
                    } else {
                        // Get the current path directory, removing any trailing slashes
                        $path = $this->get_uri()->get_path();
                        $path = rtrim(substr((string) $path, 0, strrpos((string) $path, '/')), '/');
                        $this->get_uri()->set_path($path . '/' . $location);
                    }
                }
                ++$this->redirect_counter;
            } else {
                // If we didn't get any location, stop redirecting
                break;
            }
        } while ($this->redirect_counter <= $this->config['maxredirects']);
        $this->response = $response;
        return $response;
    }
    /**
     * Fully reset the HTTP client (auth, cookies, request, response, etc.)
     *
     * @return $this
     */
    public function reset(): static
    {
        $this->reset_parameters();
        $this->clear_auth();
        $this->clear_cookies();
        return $this;
    }
    /**
     * Set a file to upload (using a POST request)
     *
     * Can be used in two ways:
     *
     * 1. $data is null (default): $filename is treated as the name if a local file which
     * will be read and sent. Will try to guess the content type using mime_content_type().
     * 2. $data is set - $filename is sent as the file name, but $data is sent as the file
     * contents and no file is read from the file system. In this case, you need to
     * manually set the Content-Type ($ctype) or it will default to
     * application/octet-stream.
     *
     * @param  string $filename Name of file to upload, or name to save as
     * @param  string $formname Name of form element to send as
     * @param  string $data Data to send (if null, $filename is read and sent)
     * @param  string $ctype Content type to use (if $data is set and $ctype is
     *                null, will be application/octet-stream)
     * @return $this
     * @throws Exception\RuntimeException
     */
    public function set_file_upload(string $filename, $formname, $data = null, $ctype = null): static
    {
        if ($data === null) {
            Error_Handler::start();
            $data = file_get_contents($filename);
            $error = Error_Handler::stop();
            if ($data === false) {
                throw new Exception\RuntimeException(sprintf('Unable to read file \'%s\' for upload', $filename), 0, $error);
            }
            if (!$ctype) {
                $ctype = $this->detect_file_mime_type($filename);
            }
        }
        $this->get_request()->get_files()->set($filename, ['formname' => $formname, 'filename' => basename($filename), 'ctype' => $ctype, 'data' => $data]);
        return $this;
    }
    /**
     * Remove a file to upload
     *
     * @param  string $filename
     */
    public function remove_file_upload($filename): bool
    {
        $file = $this->get_request()->get_files()->get($filename);
        if (!empty($file)) {
            $this->get_request()->get_files()->set($filename, null);
            return true;
        }
        return false;
    }
    /**
     * Prepare Cookies
     *
     * @param   string $domain
     * @param   string $path
     * @param   bool $secure
     * @return  Header\Cookie|bool
     */
    protected function prepare_cookies($domain, $path, $secure)
    {
        $valid_cookies = [];
        foreach ($this->cookies as $id => $cookie) {
            if ($cookie->is_expired()) {
                unset($this->cookies[$id]);
                continue;
            }
            if ($cookie->is_valid_for_request($domain, $path, $secure)) {
                // OAM hack some domains try to set the cookie multiple times
                $valid_cookies[$cookie->get_name()] = $cookie;
            }
        }
        $cookies = Header\Cookie::from_set_cookie_array($valid_cookies);
        $cookies->set_encode_value($this->config['encodecookies']);
        return $cookies;
    }
    /**
     * Prepare the request headers
     *
     * @param resource|string $body
     * @param Http $uri
     * @throws Exception\RuntimeException
     */
    protected function prepare_headers($body, $uri): array
    {
        $headers = [];
        // Set the host header
        if ($this->config['httpversion'] === Request::VERSION_11) {
            $host = $uri->get_host();
            // If the port is not default, add it
            if (!($uri->get_scheme() === 'http' && $uri->get_port() === 80 || $uri->get_scheme() === 'https' && $uri->get_port() === 443)) {
                $host .= ':' . $uri->get_port();
            }
            $headers['Host'] = $host;
        }
        // Set the connection header
        if (!$this->get_request()->get_headers()->has('Connection')) {
            if (!$this->config['keepalive']) {
                $headers['Connection'] = 'close';
            }
        }
        // Set the Accept-encoding header if not set - depending on whether
        // zlib is available or not.
        if (!$this->get_request()->get_headers()->has('Accept-Encoding')) {
            if (empty($this->config['outputstream']) && function_exists('gzinflate')) {
                $headers['Accept-Encoding'] = 'gzip, deflate';
            } else {
                $headers['Accept-Encoding'] = 'identity';
            }
        }
        // Set the user agent header
        if (!$this->get_request()->get_headers()->has('User-Agent') && isset($this->config['useragent'])) {
            $headers['User-Agent'] = $this->config['useragent'];
        }
        // Set HTTP authentication if needed
        if (!empty($this->auth)) {
            switch ($this->auth['type']) {
                case self::AUTH_BASIC:
                    $auth = $this->calc_auth_digest($this->auth['user'], $this->auth['password'], $this->auth['type']);
                    if ($auth !== false) {
                        $headers['Authorization'] = 'Basic ' . $auth;
                    }
                    break;
                case self::AUTH_DIGEST:
                    if (!$this->adapter instanceof Client\Adapter\Curl) {
                        throw new Exception\RuntimeException(sprintf('The digest authentication is only available for curl adapters (%s)', Curl::class));
                    }
                    $this->adapter->set_curl_option(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    $this->adapter->set_curl_option(CURLOPT_USERPWD, $this->auth['user'] . ':' . $this->auth['password']);
            }
        }
        // Content-type
        $enc_type = $this->get_enc_type();
        if (!empty($enc_type)) {
            $headers['Content-Type'] = $enc_type;
        }
        if (!empty($body)) {
            if (is_resource($body)) {
                $fstat = fstat($body);
                $headers['Content-Length'] = $fstat['size'];
            } else {
                $headers['Content-Length'] = strlen($body);
            }
        }
        // Merge the headers of the request (if any)
        // here we need right 'http field' and not lowercase letters
        $request_headers = $this->get_request()->get_headers();
        foreach ($request_headers as $request_header_element) {
            $headers[$request_header_element->get_field_name()] = $request_header_element->get_field_value();
        }
        return $headers;
    }
    /**
     * Prepare the request body (for PATCH, POST and PUT requests)
     *
     * @return string
     * @throws RuntimeException
     */
    protected function prepare_body()
    {
        // According to RFC2616, a TRACE request should not have a body.
        if ($this->get_request()->is_trace()) {
            return '';
        }
        $raw_body = $this->get_request()->get_content();
        if (!empty($raw_body)) {
            return $raw_body;
        }
        $body = '';
        $has_files = false;
        if (!$this->get_request()->get_headers()->has('Content-Type')) {
            $has_files = !empty($this->get_request()->get_files()->to_array());
            // If we have files to upload, force encType to multipart/form-data
            if ($has_files) {
                $this->set_enc_type(self::ENC_FORMDATA);
            }
        } else {
            $this->set_enc_type($this->get_header('Content-Type'));
        }
        // If we have POST parameters or files, encode and add them to the body
        if (!empty($this->get_request()->get_post()->to_array()) || $has_files) {
            if (stripos($this->get_enc_type(), self::ENC_FORMDATA) === 0) {
                $boundary = '---ZENDHTTPCLIENT-' . md5(microtime());
                $this->set_enc_type(self::ENC_FORMDATA, $boundary);
                // Get POST parameters and encode them
                $params = self::flatten_parameters_array($this->get_request()->get_post()->to_array());
                foreach ($params as $pp) {
                    $body .= $this->encode_form_data($boundary, $pp[0], $pp[1]);
                }
                // Encode files
                foreach ($this->get_request()->get_files()->to_array() as $file) {
                    $fhead = ['Content-Type' => $file['ctype']];
                    $body .= $this->encode_form_data($boundary, $file['formname'], $file['data'], $file['filename'], $fhead);
                }
                $body .= '--' . $boundary . '--' . "\r\n";
            } elseif (stripos($this->get_enc_type(), self::ENC_URLENCODED) === 0) {
                // Encode body as application/x-www-form-urlencoded
                $body = http_build_query($this->get_request()->get_post()->to_array(), '', '&');
            } else {
                throw new RuntimeException(sprintf('Cannot handle content type \'%s\' automatically', $this->enc_type));
            }
        }
        return $body;
    }
    /**
     * Attempt to detect the MIME type of a file using available extensions
     *
     * This method will try to detect the MIME type of a file. If the fileinfo
     * extension is available, it will be used. If not, the mime_magic
     * extension which is deprecated but is still available in many PHP setups
     * will be tried.
     *
     * If neither extension is available, the default application/octet-stream
     * MIME type will be returned
     *
     * @param string $file File path
     * @return string MIME type
     */
    protected function detect_file_mime_type($file): string
    {
        $type = null;
        // First try with fileinfo functions
        if (function_exists('finfo_open')) {
            if (static::$file_info_db === null) {
                Error_Handler::start();
                static::$file_info_db = finfo_open(FILEINFO_MIME);
                Error_Handler::stop();
            }
            if (static::$file_info_db) {
                $type = finfo_file(static::$file_info_db, $file);
            }
        } elseif (function_exists('mime_content_type')) {
            $type = mime_content_type($file);
        }
        // Fallback to the default application/octet-stream
        if (!$type) {
            return 'application/octet-stream';
        }
        return $type;
    }
    /**
     * Encode data to a multipart/form-data part suitable for a POST request.
     *
     * @param mixed $value
     * @param string $filename
     * @param array $headers Associative array of optional headers @example ("Content-Transfer-Encoding" => "binary")
     */
    public function encode_form_data(string $boundary, string $name, string $value, $filename = null, $headers = []): string
    {
        // Sanitize $name and $filename: strip double-quotes and CRLF to prevent header injection
        $name = str_replace(['"', "\r", "\n"], '', $name);
        $ret = '--' . $boundary . "\r\n" . 'Content-Disposition: form-data; name="' . $name . '"';
        if ($filename) {
            $filename = str_replace(['"', "\r", "\n"], '', $filename);
            $ret .= '; filename="' . $filename . '"';
        }
        $ret .= "\r\n";
        foreach ($headers as $hname => $hvalue) {
            $ret .= $hname . ': ' . $hvalue . "\r\n";
        }
        $ret .= "\r\n";
        return $ret . ($value . "\r\n");
    }
    /**
     * Convert an array of parameters into a flat array of (key, value) pairs
     *
     * Will flatten a potentially multi-dimentional array of parameters (such
     * as POST parameters) into a flat array of (key, value) paris. In case
     * of multi-dimentional arrays, square brackets ([]) will be added to the
     * key to indicate an array.
     *
     * @since 1.9
     * @param array $parray
     * @param string $prefix
     * @return array
     */
    protected function flatten_parameters_array($parray, $prefix = null)
    {
        if (!is_array($parray)) {
            return $parray;
        }
        $parameters = [];
        foreach ($parray as $name => $value) {
            // Calculate array key
            if ($prefix) {
                if (is_int($name)) {
                    $key = $prefix . '[]';
                } else {
                    $key = $prefix . sprintf('[%s]', $name);
                }
            } else {
                $key = $name;
            }
            if (is_array($value)) {
                $parameters = array_merge($parameters, $this->flatten_parameters_array($value, $key));
            } else {
                $parameters[] = [$key, $value];
            }
        }
        return $parameters;
    }
    /**
     * Separating this from send method allows subclasses to wrap
     * the interaction with the adapter
     *
     * @param string $method
     * @param  bool $secure
     * @param array $headers
     * @param string $body
     * @return string the raw response
     * @throws Exception\RuntimeException
     */
    protected function do_request(Http $uri, $method, $secure = false, $headers = [], $body = '')
    {
        // Open the connection, send the request and read the response
        $this->adapter->connect($uri->get_host(), $uri->get_port(), $secure);
        if ($this->config['outputstream']) {
            if ($this->adapter instanceof Client\Adapter\Stream_Interface) {
                $this->stream_handle = $this->open_temp_stream();
                $this->adapter->set_output_stream($this->stream_handle);
            } else {
                throw new Exception\RuntimeException('Adapter does not support streaming');
            }
        }
        // HTTP connection
        $this->last_raw_request = $this->adapter->write($method, $uri, $this->config['httpversion'], $headers, $body);
        return $this->adapter->read();
    }
    /**
     * Create a HTTP authentication "Authorization:" header according to the
     * specified user, password and authentication method.
     *
     * @see http://www.faqs.org/rfcs/rfc2617.html
     *
     * @param string $type
     * @throws Client\Exception\InvalidArgumentException
     */
    public static function encode_auth_header(string $user, string $password, $type = self::AUTH_BASIC): string
    {
        switch ($type) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (str_contains($user, ':')) {
                    throw new Client\Exception\InvalidArgumentException('The user name cannot contain \':\' in \'Basic\' HTTP authentication');
                }
                return 'Basic ' . base64_encode($user . ':' . $password);
            //case self::AUTH_DIGEST:
            /**
             * @todo Implement digest authentication
             */
            //    break;
            default:
                throw new Client\Exception\InvalidArgumentException(sprintf('Not a supported HTTP authentication type: \'%s\'', $type));
        }
    }
}