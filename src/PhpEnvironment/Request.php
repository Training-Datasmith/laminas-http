<?php

declare (strict_types=1);
namespace Laminas\Http\Php_Environment;

use function basename;
use function dirname;
use function file_get_contents;
use function function_exists;
use function is_array;
use function is_string;
use Laminas\Http\Header\Cookie;
use Laminas\Http\Request as HttpRequest;
use Laminas\Stdlib\Parameters;
use Laminas\Stdlib\Parameters_Interface;
use Laminas\Uri\Http as HttpUri;
use Laminas\Validator\Hostname as HostnameValidator;
use const PHP_SAPI;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function strtr;
use function substr;
use function trim;
use function ucfirst;
use function ucwords;
/**
 * HTTP Request for current PHP environment
 */
class Request extends Http_Request
{
    /**
     * Base URL of the application.
     *
     * @var string
     */
    protected $base_url;
    /**
     * Base Path of the application.
     *
     * @var string
     */
    protected $base_path;
    /**
     * Actual request URI, independent of the platform.
     *
     * @var string
     */
    protected $request_uri;
    /**
     * PHP server params ($_SERVER)
     *
     * @var ParametersInterface
     */
    protected $server_params;
    /**
     * PHP environment params ($_ENV)
     *
     * @var ParametersInterface
     */
    protected $env_params;
    /**
     * Construct
     * Instantiates request.
     *
     * @param bool $allowCustomMethods
     */
    public function __construct($allow_custom_methods = true)
    {
        $this->set_allow_custom_methods($allow_custom_methods);
        $this->set_env(new Parameters($_ENV));
        if ($_GET) {
            $this->set_query(new Parameters($_GET));
        }
        if ($_POST) {
            $this->set_post(new Parameters($_POST));
        }
        if ($_COOKIE) {
            $this->set_cookies(new Parameters($_COOKIE));
        }
        if ($_FILES) {
            // convert PHP $_FILES superglobal
            $files = $this->map_php_files();
            $this->set_files(new Parameters($files));
        }
        $this->set_server(new Parameters($_SERVER));
    }
    /**
     * Get raw request body
     *
     * @return string
     */
    public function get_content()
    {
        if (empty($this->content)) {
            $request_body = file_get_contents('php://input');
            if (strlen($request_body) > 0) {
                $this->content = $request_body;
            }
        }
        return $this->content;
    }
    /**
     * Set cookies
     *
     * Instantiate and set cookies.
     *
     * @param string|array<string, string> $cookie
     * @return $this
     */
    public function set_cookies($cookie)
    {
        $this->get_headers()->add_header(new Cookie((array) $cookie));
        return $this;
    }
    /**
     * Set the request URI.
     *
     * @param  string $requestUri
     * @return $this
     */
    public function set_request_uri($request_uri)
    {
        $this->request_uri = $request_uri;
        return $this;
    }
    /**
     * Get the request URI.
     *
     * @return string
     */
    public function get_request_uri()
    {
        if ($this->request_uri === null) {
            $this->request_uri = $this->detect_request_uri();
        }
        return $this->request_uri;
    }
    /**
     * Set the base URL.
     *
     * @param  string $baseUrl
     * @return $this
     */
    public function set_base_url($base_url)
    {
        $this->base_url = rtrim($base_url, '/');
        return $this;
    }
    /**
     * Get the base URL.
     *
     * @return string
     */
    public function get_base_url()
    {
        if ($this->base_url === null) {
            $this->set_base_url($this->detect_base_url());
        }
        return $this->base_url;
    }
    /**
     * Set the base path.
     *
     * @param  string $basePath
     * @return $this
     */
    public function set_base_path($base_path)
    {
        $this->base_path = rtrim($base_path, '/');
        return $this;
    }
    /**
     * Get the base path.
     *
     * @return string
     */
    public function get_base_path()
    {
        if ($this->base_path === null) {
            $this->set_base_path($this->detect_base_path());
        }
        return $this->base_path;
    }
    /**
     * Provide an alternate Parameter Container implementation for server parameters in this object,
     * (this is NOT the primary API for value setting, for that see getServer())
     *
     * @return $this
     */
    public function set_server(Parameters_Interface $server)
    {
        $this->server_params = $server;
        // This seems to be the only way to get the Authorization header on Apache
        if (function_exists('apache_request_headers')) {
            $apache_request_headers = apache_request_headers();
            if (!isset($this->server_params['HTTP_AUTHORIZATION'])) {
                if (isset($apache_request_headers['Authorization'])) {
                    $this->server_params->set('HTTP_AUTHORIZATION', $apache_request_headers['Authorization']);
                } elseif (isset($apache_request_headers['authorization'])) {
                    $this->server_params->set('HTTP_AUTHORIZATION', $apache_request_headers['authorization']);
                }
            }
        }
        // set headers
        $headers = [];
        foreach ($server as $key => $value) {
            if ($value || !is_array($value) && strlen($value ?? '')) {
                if (str_starts_with($key, 'HTTP_')) {
                    if (str_starts_with($key, 'HTTP_COOKIE')) {
                        // Cookies are handled using the $_COOKIE superglobal
                        continue;
                    }
                    $headers[strtr(ucwords(strtolower(strtr(substr($key, 5), '_', ' '))), ' ', '-')] = $value;
                } elseif (str_starts_with($key, 'CONTENT_')) {
                    $name = substr($key, 8);
                    // Remove "Content-"
                    $headers['Content-' . ($name === 'MD5' ? $name : ucfirst(strtolower($name)))] = $value;
                }
            }
        }
        $this->get_headers()->add_headers($headers);
        // set method
        if (isset($this->server_params['REQUEST_METHOD'])) {
            $this->set_method($this->server_params['REQUEST_METHOD']);
        }
        // set HTTP version
        if (isset($this->server_params['SERVER_PROTOCOL']) && str_contains($this->server_params['SERVER_PROTOCOL'], self::VERSION_10)) {
            $this->set_version(self::VERSION_10);
        }
        // set URI
        $uri = new Http_Uri();
        // URI scheme
        if (!empty($this->server_params['HTTPS']) && strtolower((string) $this->server_params['HTTPS']) !== 'off' || !empty($this->server_params['HTTP_X_FORWARDED_PROTO']) && $this->server_params['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }
        $uri->set_scheme($scheme);
        // URI host & port
        $host = null;
        $port = null;
        // Set the host
        $header_host = $this->get_headers()->get('host');
        if ($header_host) {
            $host = $header_host->get_field_value();
            // works for regname, IPv4 & IPv6
            if (preg_match('|\:(\d+)$|', $host, $matches)) {
                $host = substr($host, 0, -1 * (strlen($matches[1]) + 1));
                $port = (int) $matches[1];
            }
            // set up a validator that check if the hostname is legal (not spoofed)
            $hostname_validator = new Hostname_Validator(['allow' => Hostname_Validator::ALLOW_ALL, 'useIdnCheck' => false, 'useTldCheck' => false]);
            // If invalid. Reset the host & port
            if (!$hostname_validator->is_valid($host)) {
                $host = null;
                $port = null;
            }
        }
        if (!$host && isset($this->server_params['SERVER_NAME'])) {
            $host = $this->server_params['SERVER_NAME'];
            if (isset($this->server_params['SERVER_PORT'])) {
                $port = (int) $this->server_params['SERVER_PORT'];
            }
            // Check for missinterpreted IPv6-Address
            // Reported at least for Safari on Windows
            if (isset($this->server_params['SERVER_ADDR']) && preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host)) {
                $host = '[' . $this->server_params['SERVER_ADDR'] . ']';
                if ($port . ']' === substr($host, strrpos($host, ':') + 1)) {
                    // The last digit of the IPv6-Address has been taken as port
                    // Unset the port so the default port can be used
                    $port = null;
                }
            }
        }
        $uri->set_host($host);
        $uri->set_port($port);
        // URI path
        $request_uri = $this->get_request_uri();
        if (($qpos = strpos($request_uri, '?')) !== false) {
            $request_uri = substr($request_uri, 0, $qpos);
        }
        $uri->set_path($request_uri);
        // URI query
        if (isset($this->server_params['QUERY_STRING'])) {
            $uri->set_query($this->server_params['QUERY_STRING']);
        }
        $this->set_uri($uri);
        return $this;
    }
    /**
     * Return the parameter container responsible for server parameters or a single parameter value.
     *
     * @see http://www.faqs.org/rfcs/rfc3875.html
     *
     * @param string|null           $name            Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null            $default         Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function get_server($name = null, $default = null)
    {
        if ($this->server_params === null) {
            $this->server_params = new Parameters();
        }
        if ($name === null) {
            return $this->server_params;
        }
        return $this->server_params->get($name, $default);
    }
    /**
     * Provide an alternate Parameter Container implementation for env parameters in this object,
     * (this is NOT the primary API for value setting, for that see env())
     *
     * @return $this
     */
    public function set_env(Parameters_Interface $env)
    {
        $this->env_params = $env;
        return $this;
    }
    /**
     * Return the parameter container responsible for env parameters or a single parameter value.
     *
     * @param string|null           $name            Parameter name to retrieve, or null to get the whole container.
     * @param mixed|null            $default         Default value to use when the parameter is missing.
     * @return ParametersInterface|mixed
     */
    public function get_env($name = null, $default = null)
    {
        if ($this->env_params === null) {
            $this->env_params = new Parameters();
        }
        if ($name === null) {
            return $this->env_params;
        }
        return $this->env_params->get($name, $default);
    }
    /**
     * Convert PHP superglobal $_FILES into more sane parameter=value structure
     * This handles form file input with brackets (name=files[])
     *
     * @return array
     */
    protected function map_php_files()
    {
        $files = [];
        foreach ($_FILES as $file_name => $file_params) {
            $files[$file_name] = [];
            foreach ($file_params as $param => $data) {
                if (!is_array($data)) {
                    $files[$file_name][$param] = $data;
                } else {
                    foreach ($data as $i => $v) {
                        $this->map_php_file_param($files[$file_name], $param, $i, $v);
                    }
                }
            }
        }
        return $files;
    }
    /**
     * @param array        $array
     * @param string       $paramName
     * @param int|string   $index
     * @param string|array $value
     */
    protected function map_php_file_param(&$array, $param_name, $index, $value)
    {
        if (!is_array($value)) {
            $array[$index][$param_name] = $value;
        } else {
            foreach ($value as $i => $v) {
                $this->map_php_file_param($array[$index], $param_name, $i, $v);
            }
        }
    }
    /**
     * Detect the base URI for the request
     *
     * Looks at a variety of criteria in order to attempt to autodetect a base
     * URI, including rewrite URIs, proxy URIs, etc.
     *
     * @return string
     */
    protected function detect_request_uri()
    {
        $request_uri = null;
        $server = $this->get_server();
        // IIS7 with URL Rewrite: make sure we get the unencoded url
        // (double slash problem).
        $iis_url_rewritten = $server->get('IIS_WasUrlRewritten');
        $unencoded_url = $server->get('UNENCODED_URL', '');
        if ('1' === $iis_url_rewritten && '' !== $unencoded_url) {
            return $unencoded_url;
        }
        $request_uri = $server->get('REQUEST_URI');
        // HTTP proxy requests setup request URI with scheme and host [and port]
        // + the URL path, only use URL path.
        if ($request_uri !== null) {
            return preg_replace('#^[^/:]+://[^/]+#', '', $request_uri);
        }
        // IIS 5.0, PHP as CGI.
        $orig_path_info = $server->get('ORIG_PATH_INFO');
        if ($orig_path_info !== null) {
            $query_string = $server->get('QUERY_STRING', '');
            if ($query_string !== '') {
                $orig_path_info .= '?' . $query_string;
            }
            return $orig_path_info;
        }
        return '/';
    }
    /**
     * Auto-detect the base path from the request environment
     *
     * Uses a variety of criteria in order to detect the base URL of the request
     * (i.e., anything additional to the document root).
     *
     * @return string
     */
    protected function detect_base_url()
    {
        $filename = $this->get_server()->get('SCRIPT_FILENAME', '');
        $script_name = $this->get_server()->get('SCRIPT_NAME');
        $php_self = $this->get_server()->get('PHP_SELF');
        $orig_script_name = $this->get_server()->get('ORIG_SCRIPT_NAME');
        if ($script_name !== null && basename($script_name) === $filename) {
            $base_url = $script_name;
        } elseif ($php_self !== null && basename($php_self) === $filename) {
            $base_url = $php_self;
        } elseif ($orig_script_name !== null && basename($orig_script_name) === $filename) {
            // 1and1 shared hosting compatibility.
            $base_url = $orig_script_name;
        } else {
            // Backtrack up the SCRIPT_FILENAME to find the portion
            // matching PHP_SELF.
            // Only for CLI requests argv[0] contains script filename
            // @see https://www.php.net/manual/en/reserved.variables.server.php
            if (PHP_SAPI === 'cli') {
                $argv = $this->get_server()->get('argv', []);
                if (isset($argv[0]) && is_string($argv[0]) && $argv[0] !== '' && str_starts_with((string) $filename, $argv[0])) {
                    $filename = substr((string) $filename, strlen($argv[0]));
                }
            }
            $base_url = '/';
            $basename = basename($filename ?? '');
            if ($basename) {
                $path = $php_self ? trim((string) $php_self, '/') : '';
                $base_pos = strpos($path, $basename) ?: 0;
                $base_url .= substr($path, 0, $base_pos) . $basename;
            }
        }
        // If the baseUrl is empty, then simply return it.
        if (empty($base_url)) {
            return '';
        }
        // Does the base URL have anything in common with the request URI?
        $request_uri = $this->get_request_uri();
        // Full base URL matches.
        if (str_starts_with($request_uri, (string) $base_url)) {
            return $base_url;
        }
        // Directory portion of base path matches.
        $base_dir = str_replace('\\', '/', dirname((string) $base_url));
        if (str_starts_with($request_uri, $base_dir)) {
            return $base_dir;
        }
        $truncated_request_uri = $request_uri;
        if (false !== $pos = strpos($request_uri, '?')) {
            $truncated_request_uri = substr($request_uri, 0, $pos);
        }
        $basename = basename((string) $base_url);
        // No match whatsoever
        if (empty($basename) || !str_contains($truncated_request_uri, $basename)) {
            return '';
        }
        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of the base path. $pos !== 0 makes sure it is not matching a
        // value from PATH_INFO or QUERY_STRING.
        if (strlen($request_uri) >= strlen((string) $base_url) && (false !== ($pos = strpos($request_uri, (string) $base_url)) && $pos !== 0)) {
            return substr($request_uri, 0, $pos + strlen((string) $base_url));
        }
        return $base_url;
    }
    /**
     * Autodetect the base path of the request
     *
     * Uses several criteria to determine the base path of the request.
     *
     * @return string
     */
    protected function detect_base_path()
    {
        $base_url = $this->get_base_url();
        // Empty base url detected
        if ($base_url === '') {
            return '';
        }
        $filename = basename((string) $this->get_server()->get('SCRIPT_FILENAME', ''));
        // basename() matches the script filename; return the directory
        if (basename($base_url) === $filename) {
            return str_replace('\\', '/', dirname($base_url));
        }
        // Base path is identical to base URL
        return $base_url;
    }
}