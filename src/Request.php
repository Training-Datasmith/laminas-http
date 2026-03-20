<?php

declare (strict_types=1);
namespace Laminas\Http;

use function array_key_exists;
use function array_shift;
use ArrayIterator;
use function defined;
use function explode;
use function implode;
use function is_string;
use Laminas\Http\Header\Header_Interface;
use Laminas\Stdlib\Parameters;
use Laminas\Stdlib\Parameters_Interface;
use Laminas\Stdlib\Request_Interface;
use Laminas\Uri\Exception as UriException;
use Laminas\Uri\Http as HttpUri;
use function parse_str;
use function parse_url;
use function preg_match;
use function sprintf;
use function stristr;
use function strtoupper;
/**
 * HTTP Request
 *
 * @link      http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5
 */
class Request extends Abstract_Message implements Request_Interface
{
    /**#@+
     *
     * @const string METHOD constant names
     */
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_GET = 'GET';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_TRACE = 'TRACE';
    public const METHOD_CONNECT = 'CONNECT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_PROPFIND = 'PROPFIND';
    /**#@-*/
    /** @var string */
    protected $method = self::METHOD_GET;
    /** @var bool */
    protected $allow_custom_methods = true;
    /** @var string|HttpUri */
    protected $uri;
    /** @var ParametersInterface */
    protected $query_params;
    /** @var ParametersInterface */
    protected $post_params;
    /** @var ParametersInterface */
    protected $file_params;
    /**
     * A factory that produces a Request object from a well-formed Http Request string
     *
     * @param  string $string
     * @param  bool $allowCustomMethods
     * @throws Exception\InvalidArgumentException
     * @return static
     */
    public static function from_string($string, $allow_custom_methods = true)
    {
        $request = new static();
        $request->set_allow_custom_methods($allow_custom_methods);
        $lines = explode("\r\n", $string);
        // first line must be Method/Uri/Version string
        $matches = null;
        $methods = $allow_custom_methods ? '[\w-]+' : implode('|', [self::METHOD_OPTIONS, self::METHOD_GET, self::METHOD_HEAD, self::METHOD_POST, self::METHOD_PUT, self::METHOD_DELETE, self::METHOD_TRACE, self::METHOD_CONNECT, self::METHOD_PATCH]);
        $regex = '#^(?P<method>' . $methods . ')\s(?P<uri>[^ ]*)(?:\sHTTP\/(?P<version>\d+\.\d+)){0,1}#';
        $first_line = array_shift($lines);
        if (!preg_match($regex, $first_line, $matches)) {
            throw new Exception\InvalidArgumentException('A valid request line was not found in the provided string');
        }
        $request->set_method($matches['method']);
        $request->set_uri($matches['uri']);
        $parsed_uri = parse_url($matches['uri']);
        if (array_key_exists('query', $parsed_uri)) {
            $parsed_query = [];
            parse_str($parsed_uri['query'], $parsed_query);
            $request->set_query(new Parameters($parsed_query));
        }
        if (isset($matches['version'])) {
            $request->set_version($matches['version']);
        }
        if (empty($lines)) {
            return $request;
        }
        $is_header = true;
        $headers = $raw_body = [];
        while ($lines) {
            $next_line = array_shift($lines);
            if ($next_line === '') {
                $is_header = false;
                continue;
            }
            if ($is_header) {
                if (preg_match("/[\r\n]/", $next_line)) {
                    throw new Exception\RuntimeException('CRLF injection detected');
                }
                $headers[] = $next_line;
                continue;
            }
            if (empty($raw_body) && preg_match('/^[a-z0-9!#$%&\'*+.^_`|~-]+:$/i', $next_line)) {
                throw new Exception\RuntimeException('CRLF injection detected');
            }
            $raw_body[] = $next_line;
        }
        if ($headers) {
            $request->headers = implode("\r\n", $headers);
        }
        if ($raw_body) {
            $request->set_content(implode("\r\n", $raw_body));
        }
        return $request;
    }
    /**
     * Set the method for this request
     *
     * @param  string $method
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function set_method($method)
    {
        $method = strtoupper($method);
        if (!defined('static::METHOD_' . $method) && !$this->get_allow_custom_methods()) {
            throw new Exception\InvalidArgumentException('Invalid HTTP method passed');
        }
        $this->method = $method;
        return $this;
    }
    /**
     * Return the method for this request
     *
     * @return string
     */
    public function get_method()
    {
        return $this->method;
    }
    /**
     * Set the URI/URL for this request, this can be a string or an instance of Laminas\Uri\Http
     *
     * @throws Exception\InvalidArgumentException
     * @param string|HttpUri $uri
     * @return $this
     */
    public function set_uri($uri)
    {
        if (is_string($uri)) {
            try {
                $uri = new Http_Uri($uri);
            } catch (Uri_Exception\Invalid_Uri_Part_Exception $e) {
                throw new Exception\InvalidArgumentException(sprintf('Invalid URI passed as string (%s)', $uri), $e->get_code(), $e);
            }
        } elseif (!$uri instanceof Http_Uri) {
            throw new Exception\InvalidArgumentException('URI must be an instance of Laminas\Uri\Http or a string');
        }
        $this->uri = $uri;
        return $this;
    }
    /**
     * Return the URI for this request object
     *
     * @return HttpUri
     */
    public function get_uri()
    {
        if ($this->uri === null || is_string($this->uri)) {
            $this->uri = new Http_Uri($this->uri);
        }
        return $this->uri;
    }
    /**
     * Return the URI for this request object as a string
     *
     * @return string
     */
    public function get_uri_string()
    {
        if ($this->uri instanceof Http_Uri) {
            return $this->uri->to_string();
        }
        return $this->uri;
    }
    /**
     * Provide an alternate Parameter Container implementation for query parameters in this object,
     * (this is NOT the primary API for value setting, for that see getQuery())
     *
     * @return $this
     */
    public function set_query(Parameters_Interface $query)
    {
        $this->query_params = $query;
        return $this;
    }
    /**
     * Return the parameter container responsible for query parameters or a single query parameter
     *
     * @param  string|null $name    Parameter name to retrieve, or null to get the whole container.
     * @param  mixed|null  $default Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function get_query($name = null, $default = null)
    {
        if ($this->query_params === null) {
            $this->query_params = new Parameters();
        }
        if ($name === null) {
            return $this->query_params;
        }
        return $this->query_params->get($name, $default);
    }
    /**
     * Provide an alternate Parameter Container implementation for post parameters in this object,
     * (this is NOT the primary API for value setting, for that see getPost())
     *
     * @return $this
     */
    public function set_post(Parameters_Interface $post)
    {
        $this->post_params = $post;
        return $this;
    }
    /**
     * Return the parameter container responsible for post parameters or a single post parameter.
     *
     * @param  string|null $name    Parameter name to retrieve, or null to get the whole container.
     * @param  mixed|null  $default Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function get_post($name = null, $default = null)
    {
        if ($this->post_params === null) {
            $this->post_params = new Parameters();
        }
        if ($name === null) {
            return $this->post_params;
        }
        return $this->post_params->get($name, $default);
    }
    /**
     * Return the Cookie header, this is the same as calling $request->getHeaders()->get('Cookie');
     *
     * @convenience $request->getHeaders()->get('Cookie');
     * @return Header\Cookie|bool
     */
    public function get_cookie()
    {
        return $this->get_headers()->get('Cookie');
    }
    /**
     * Provide an alternate Parameter Container implementation for file parameters in this object,
     * (this is NOT the primary API for value setting, for that see getFiles())
     *
     * @return $this
     */
    public function set_files(Parameters_Interface $files)
    {
        $this->file_params = $files;
        return $this;
    }
    /**
     * Return the parameter container responsible for file parameters or a single file.
     *
     * @param  string|null $name    Parameter name to retrieve, or null to get the whole container.
     * @param  mixed|null  $default Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function get_files($name = null, $default = null)
    {
        if ($this->file_params === null) {
            $this->file_params = new Parameters();
        }
        if ($name === null) {
            return $this->file_params;
        }
        return $this->file_params->get($name, $default);
    }
    /**
     * Return the header container responsible for headers or all headers of a certain name/type
     *
     * @see \Laminas\Http\Headers::get()
     *
     * @param  string|null $name    Header name to retrieve, or null to get the whole container.
     * @param  mixed|null  $default Default value to use when the requested header is missing.
     * @return Headers|bool|HeaderInterface|ArrayIterator
     */
    public function get_headers($name = null, $default = false)
    {
        if ($this->headers === null || is_string($this->headers)) {
            // this is only here for fromString lazy loading
            $this->headers = is_string($this->headers) ? Headers::from_string($this->headers) : new Headers();
        }
        if ($name === null) {
            return $this->headers;
        }
        if ($this->headers->has($name)) {
            return $this->headers->get($name);
        }
        return $default;
    }
    /**
     * Get all headers of a certain name/type.
     *
     * @see Request::getHeaders()
     *
     * @param string|null           $name            Header name to retrieve, or null to get the whole container.
     * @param mixed|null            $default         Default value to use when the requested header is missing.
     * @return Headers|bool|HeaderInterface|ArrayIterator
     */
    public function get_header($name, $default = false)
    {
        return $this->get_headers($name, $default);
    }
    /**
     * Is this an OPTIONS method request?
     *
     * @return bool
     */
    public function is_options()
    {
        return $this->method === self::METHOD_OPTIONS;
    }
    /**
     * Is this a PROPFIND method request?
     *
     * @return bool
     */
    public function is_prop_find()
    {
        return $this->method === self::METHOD_PROPFIND;
    }
    /**
     * Is this a GET method request?
     *
     * @return bool
     */
    public function is_get()
    {
        return $this->method === self::METHOD_GET;
    }
    /**
     * Is this a HEAD method request?
     *
     * @return bool
     */
    public function is_head()
    {
        return $this->method === self::METHOD_HEAD;
    }
    /**
     * Is this a POST method request?
     *
     * @return bool
     */
    public function is_post()
    {
        return $this->method === self::METHOD_POST;
    }
    /**
     * Is this a PUT method request?
     *
     * @return bool
     */
    public function is_put()
    {
        return $this->method === self::METHOD_PUT;
    }
    /**
     * Is this a DELETE method request?
     *
     * @return bool
     */
    public function is_delete()
    {
        return $this->method === self::METHOD_DELETE;
    }
    /**
     * Is this a TRACE method request?
     *
     * @return bool
     */
    public function is_trace()
    {
        return $this->method === self::METHOD_TRACE;
    }
    /**
     * Is this a CONNECT method request?
     *
     * @return bool
     */
    public function is_connect()
    {
        return $this->method === self::METHOD_CONNECT;
    }
    /**
     * Is this a PATCH method request?
     *
     * @return bool
     */
    public function is_patch()
    {
        return $this->method === self::METHOD_PATCH;
    }
    /**
     * Is the request a Javascript XMLHttpRequest?
     *
     * Should work with Prototype/Script.aculo.us, possibly others.
     *
     * @return bool
     */
    public function is_xml_http_request()
    {
        $header = $this->get_headers()->get('X_REQUESTED_WITH');
        return false !== $header && $header->get_field_value() === 'XMLHttpRequest';
    }
    /**
     * Is this a Flash request?
     *
     * @return bool
     */
    public function is_flash_request()
    {
        $header = $this->get_headers()->get('USER_AGENT');
        return false !== $header && stristr($header->get_field_value(), ' flash');
    }
    /**
     * Return the formatted request line (first line) for this http request
     *
     * @return string
     */
    public function render_request_line()
    {
        return $this->method . ' ' . $this->uri . ' HTTP/' . $this->version;
    }
    /**
     * @return string
     */
    public function to_string()
    {
        $str = $this->render_request_line() . "\r\n";
        $str .= $this->get_headers()->to_string();
        $str .= "\r\n";
        return $str . $this->get_content();
    }
    /**
     * @return bool
     */
    public function get_allow_custom_methods()
    {
        return $this->allow_custom_methods;
    }
    /**
     * @param bool $strictMethods
     */
    public function set_allow_custom_methods($strict_methods): void
    {
        $this->allow_custom_methods = (bool) $strict_methods;
    }
}