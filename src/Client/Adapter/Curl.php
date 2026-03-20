<?php

declare (strict_types=1);
namespace Laminas\Http\Client\Adapter;

use function array_key_exists;
use function base64_decode;
use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use const CURL_HTTP_VERSION_1_0;
use const CURL_HTTP_VERSION_1_1;
use function curl_init;
use function curl_setopt;
use const CURLAUTH_BASIC;
use const CURLINFO_HEADER_OUT;
use const CURLINFO_HEADER_SIZE;
use const CURLOPT_CAINFO;
use const CURLOPT_CAPATH;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_ENCODING;
use const CURLOPT_FILE;
use const CURLOPT_HEADER;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTP_VERSION;
use const CURLOPT_HTTPAUTH;
use const CURLOPT_HTTPGET;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_INFILE;
use const CURLOPT_INFILESIZE;
use const CURLOPT_MAXREDIRS;
use const CURLOPT_NOBODY;
use const CURLOPT_PORT;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_PROXY;
use const CURLOPT_PROXYPORT;
use const CURLOPT_PROXYUSERPWD;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_SSLCERT;
use const CURLOPT_SSLCERTPASSWD;
use const CURLOPT_TIMEOUT;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_UPLOAD;
use const CURLOPT_URL;
use const CURLOPT_USERPWD;
use function defined;
use function extension_loaded;
use function gettype;
use function in_array;
use function intval;
use function is_array;
use function is_float;
use function is_numeric;
use function is_resource;
use Laminas\Http\Client\Adapter\Adapter_Interface as HttpAdapter;
use Laminas\Http\Client\Adapter\Exception as AdapterException;
use Laminas\Stdlib\Array_Utils;
use Laminas\Uri\Uri;
use function number_format;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function substr_replace;
use Traversable;
/**
 * An adapter class for Laminas\Http\Client based on the curl extension.
 * Curl requires libcurl. See for full requirements the PHP manual: http://php.net/curl
 */
class Curl implements Http_Adapter, Stream_Interface
{
    /**
     * Operation timeout.
     *
     * @var int
     */
    public const ERROR_OPERATION_TIMEDOUT = 28;
    /**
     * Parameters array
     *
     * @var array
     */
    protected $config = [];
    /**
     * What host/port are we connected to?
     *
     * @var array
     */
    protected $connected_to = [null, null];
    /**
     * The curl session handle
     *
     * @var resource|null
     */
    protected $curl;
    /**
     * List of cURL options that should never be overwritten
     *
     * @var array
     */
    protected $invalid_overwritable_curl_options;
    /**
     * Response gotten from server
     *
     * @var string
     */
    protected $response;
    /**
     * Stream for storing output
     *
     * @var resource
     */
    protected $output_stream;
    /**
     * Adapter constructor
     *
     * Config is set using setOptions()
     *
     * @throws AdapterException\InitializationException
     */
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new Adapter_Exception\Initialization_Exception('cURL extension has to be loaded to use this Laminas\Http\Client adapter');
        }
        $this->invalid_overwritable_curl_options = [CURLOPT_HTTPGET, CURLOPT_POST, CURLOPT_UPLOAD, CURLOPT_CUSTOMREQUEST, CURLOPT_HEADER, CURLOPT_RETURNTRANSFER, CURLOPT_HTTPHEADER, CURLOPT_INFILE, CURLOPT_INFILESIZE, CURLOPT_PORT, CURLOPT_MAXREDIRS, CURLOPT_CONNECTTIMEOUT];
    }
    /**
     * Set the configuration array for the adapter
     *
     * @param  array|Traversable $options
     * @return $this
     * @throws AdapterException\InvalidArgumentException
     */
    public function set_options($options = []): static
    {
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        if (!is_array($options)) {
            throw new Adapter_Exception\InvalidArgumentException(sprintf('Array or Traversable object expected, got %s', gettype($options)));
        }
        /** Config Key Normalization */
        foreach ($options as $k => $v) {
            unset($options[$k]);
            // unset original value
            $options[str_replace(['-', '_', ' ', '.'], '', strtolower((string) $k))] = $v;
            // replace w/ normalized
        }
        if (isset($options['proxyuser']) && isset($options['proxypass'])) {
            $this->set_curl_option(CURLOPT_PROXYUSERPWD, $options['proxyuser'] . ':' . $options['proxypass']);
            unset($options['proxyuser'], $options['proxypass']);
        }
        if (isset($options['sslverifypeer'])) {
            $this->set_curl_option(CURLOPT_SSL_VERIFYPEER, $options['sslverifypeer']);
            unset($options['sslverifypeer']);
        }
        foreach ($options as $k => $v) {
            $option = strtolower((string) $k);
            switch ($option) {
                case 'proxyhost':
                    $this->set_curl_option(CURLOPT_PROXY, $v);
                    break;
                case 'proxyport':
                    $this->set_curl_option(CURLOPT_PROXYPORT, $v);
                    break;
                default:
                    if (is_array($v) && isset($this->config[$option]) && is_array($this->config[$option])) {
                        $v = Array_Utils::merge($this->config[$option], $v, true);
                    }
                    $this->config[$option] = $v;
                    break;
            }
        }
        return $this;
    }
    /**
     * Retrieve the array of all configuration options
     *
     * @return array
     */
    public function get_config()
    {
        return $this->config;
    }
    /**
     * Direct setter for cURL adapter related options.
     *
     * @param  string|int $option
     * @param  mixed $value
     * @return $this
     */
    public function set_curl_option($option, $value): static
    {
        if (!isset($this->config['curloptions'])) {
            $this->config['curloptions'] = [];
        }
        $this->config['curloptions'][$option] = $value;
        return $this;
    }
    /**
     * Initialize curl
     *
     * @param  string  $host
     * @param  int     $port
     * @param  bool $secure
     * @throws AdapterException\RuntimeException If unable to connect.
     */
    public function connect($host, $port = 80, $secure = false): void
    {
        // If we're already connected, disconnect first
        if ($this->curl) {
            $this->close();
        }
        // Do the actual connection
        $this->curl = curl_init();
        if ($port !== 80) {
            curl_setopt($this->curl, CURLOPT_PORT, intval($port));
        }
        if (isset($this->config['connecttimeout'])) {
            $connect_timeout = $this->config['connecttimeout'];
        } elseif (isset($this->config['timeout'])) {
            $connect_timeout = $this->config['timeout'];
        } else {
            $connect_timeout = null;
        }
        if ($connect_timeout !== null && !is_numeric($connect_timeout)) {
            throw new Adapter_Exception\InvalidArgumentException(sprintf('integer or numeric string expected, got %s', gettype($connect_timeout)));
        }
        if ($connect_timeout !== null) {
            $connect_timeout = (int) $connect_timeout;
        }
        if ($connect_timeout !== null) {
            if (defined('CURLOPT_CONNECTTIMEOUT_MS')) {
                curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout * 1000);
            } else {
                curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
            }
        }
        if (isset($this->config['timeout'])) {
            if (defined('CURLOPT_TIMEOUT_MS')) {
                curl_setopt($this->curl, CURLOPT_TIMEOUT_MS, $this->config['timeout'] * 1000);
            } else {
                curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->config['timeout']);
            }
        }
        if (isset($this->config['sslcafile']) && $this->config['sslcafile']) {
            curl_setopt($this->curl, CURLOPT_CAINFO, $this->config['sslcafile']);
        }
        if (isset($this->config['sslcapath']) && $this->config['sslcapath']) {
            curl_setopt($this->curl, CURLOPT_CAPATH, $this->config['sslcapath']);
        }
        if (isset($this->config['maxredirects'])) {
            // Set Max redirects
            curl_setopt($this->curl, CURLOPT_MAXREDIRS, $this->config['maxredirects']);
        }
        if (!$this->curl) {
            $this->close();
            throw new Adapter_Exception\RuntimeException('Unable to Connect to ' . $host . ':' . $port);
        }
        if ($secure !== false) {
            // Behave the same like Laminas\Http\Adapter\Socket on SSL options.
            if (isset($this->config['sslcert'])) {
                curl_setopt($this->curl, CURLOPT_SSLCERT, $this->config['sslcert']);
            }
            if (isset($this->config['sslpassphrase'])) {
                curl_setopt($this->curl, CURLOPT_SSLCERTPASSWD, $this->config['sslpassphrase']);
            }
        }
        // Update connected_to
        $this->connected_to = [$host, $port];
    }
    /**
     * Send request to the remote server
     *
     * @param  string        $method
     * @param Uri $uri
     * @param  float|string  $httpVersion
     * @param  array         $headers
     * @param  string        $body
     * @return string        $request
     * @throws AdapterException\RuntimeException If connection fails, connected
     *     to wrong host, no PUT file defined, unsupported method, or unsupported
     *     cURL option.
     * @throws AdapterException\InvalidArgumentException If $method is currently not supported.
     * @throws AdapterException\TimeoutException If connection timed out.
     */
    public function write($method, $uri, $http_version = '1.1', $headers = [], $body = ''): string
    {
        if (is_float($http_version)) {
            $http_version = number_format($http_version, 1, '.', '');
        }
        // Make sure we're properly connected
        if (!$this->curl) {
            throw new Adapter_Exception\RuntimeException('Trying to write but we are not connected');
        }
        if ($this->connected_to[0] !== $uri->get_host() || $this->connected_to[1] !== $uri->get_port()) {
            throw new Adapter_Exception\RuntimeException('Trying to write but we are connected to the wrong host');
        }
        // set URL
        curl_setopt($this->curl, CURLOPT_URL, $uri->__toString());
        // ensure correct curl call
        $curl_value = true;
        switch ($method) {
            case 'GET':
                $curl_method = CURLOPT_HTTPGET;
                break;
            case 'POST':
                $curl_method = CURLOPT_POST;
                break;
            case 'PUT':
                // There are two different types of PUT request, either a Raw Data string has been set
                // or CURLOPT_INFILE and CURLOPT_INFILESIZE are used.
                if (is_resource($body)) {
                    $this->config['curloptions'][CURLOPT_INFILE] = $body;
                }
                if (isset($this->config['curloptions'][CURLOPT_INFILE])) {
                    // Now we will probably already have Content-Length set, so that we have to delete it
                    // from $headers at this point:
                    if (!isset($headers['Content-Length']) && !isset($this->config['curloptions'][CURLOPT_INFILESIZE])) {
                        throw new Adapter_Exception\RuntimeException('Cannot set a file-handle for cURL option CURLOPT_INFILE' . ' without also setting its size in CURLOPT_INFILESIZE.');
                    }
                    if (isset($headers['Content-Length'])) {
                        $this->config['curloptions'][CURLOPT_INFILESIZE] = (int) $headers['Content-Length'];
                        unset($headers['Content-Length']);
                    }
                    if (is_resource($body)) {
                        $body = '';
                    }
                    $curl_method = CURLOPT_UPLOAD;
                } else {
                    $curl_method = CURLOPT_CUSTOMREQUEST;
                    $curl_value = 'PUT';
                }
                break;
            case 'PATCH':
                $curl_method = CURLOPT_CUSTOMREQUEST;
                $curl_value = 'PATCH';
                break;
            case 'DELETE':
                $curl_method = CURLOPT_CUSTOMREQUEST;
                $curl_value = 'DELETE';
                break;
            case 'OPTIONS':
                $curl_method = CURLOPT_CUSTOMREQUEST;
                $curl_value = 'OPTIONS';
                break;
            case 'TRACE':
                $curl_method = CURLOPT_CUSTOMREQUEST;
                $curl_value = 'TRACE';
                break;
            case 'HEAD':
                $curl_method = CURLOPT_CUSTOMREQUEST;
                $curl_value = 'HEAD';
                break;
            default:
                // For now, through an exception for unsupported request methods
                throw new Adapter_Exception\InvalidArgumentException(sprintf('Method \'%s\' currently not supported', $method));
        }
        if (is_resource($body) && $curl_method !== CURLOPT_UPLOAD) {
            throw new Adapter_Exception\RuntimeException('Streaming requests are allowed only with PUT');
        }
        // get http version to use
        $curl_http = $http_version === '1.1' ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_1_0;
        // mark as HTTP request and set HTTP method
        curl_setopt($this->curl, CURLOPT_HTTP_VERSION, $curl_http);
        curl_setopt($this->curl, $curl_method, $curl_value);
        // Set the CURLOPT_NOBODY flag for HEAD HTTP method
        curl_setopt($this->curl, CURLOPT_NOBODY, $curl_method === CURLOPT_CUSTOMREQUEST && $curl_value === 'HEAD');
        // Set the CURLINFO_HEADER_OUT flag so that we can retrieve the full request string later
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
        if ($this->output_stream) {
            // headers will be read into the response
            curl_setopt($this->curl, CURLOPT_HEADER, false);
            curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, $this->read_header(...));
            // and data will be written into the file
            curl_setopt($this->curl, CURLOPT_FILE, $this->output_stream);
        } else {
            // ensure headers are also returned
            curl_setopt($this->curl, CURLOPT_HEADER, true);
            // ensure actual response is returned
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        }
        // Treating basic auth headers in a special way
        if (array_key_exists('Authorization', $headers) && str_starts_with((string) $headers['Authorization'], 'Basic')) {
            curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($this->curl, CURLOPT_USERPWD, base64_decode(substr((string) $headers['Authorization'], 6)));
            unset($headers['Authorization']);
        }
        // set additional headers
        if (!isset($headers['Accept'])) {
            $headers['Accept'] = '';
        }
        $curl_headers = [];
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $curl_headers);
        /**
         * Make sure POSTFIELDS is set after $curlMethod is set:
         *
         * @link http://de2.php.net/manual/en/function.curl-setopt.php#81161
         */
        if ($curl_method === CURLOPT_UPLOAD) {
            // this covers a PUT by file-handle:
            // Make the setting of this options explicit (rather than setting it through the loop following a bit lower)
            // to group common functionality together.
            curl_setopt($this->curl, CURLOPT_INFILE, $this->config['curloptions'][CURLOPT_INFILE]);
            curl_setopt($this->curl, CURLOPT_INFILESIZE, $this->config['curloptions'][CURLOPT_INFILESIZE]);
            unset($this->config['curloptions'][CURLOPT_INFILE]);
            unset($this->config['curloptions'][CURLOPT_INFILESIZE]);
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        }
        // set additional curl options
        if (isset($this->config['curloptions'])) {
            foreach ((array) $this->config['curloptions'] as $k => $v) {
                if (in_array($k, $this->invalid_overwritable_curl_options)) {
                    continue;
                }
                if (curl_setopt($this->curl, $k, $v) !== false) {
                    continue;
                }
                throw new Adapter_Exception\RuntimeException(sprintf('Unknown or erroreous cURL option "%s" set', $k));
            }
        }
        $this->response = '';
        // send the request
        $response = curl_exec($this->curl);
        // if we used streaming, headers are already there
        if (!is_resource($this->output_stream)) {
            $this->response = $response;
        }
        $request = curl_getinfo($this->curl, CURLINFO_HEADER_OUT);
        $request .= $body;
        if ($response === false || empty($this->response)) {
            if (curl_errno($this->curl) === static::ERROR_OPERATION_TIMEDOUT) {
                throw new Adapter_Exception\Timeout_Exception('Read timed out', Adapter_Exception\Timeout_Exception::READ_TIMEOUT);
            }
            throw new Adapter_Exception\RuntimeException(sprintf('Error in cURL request: %s', curl_error($this->curl)));
        }
        // separating header from body because it is dangerous to accidentially replace strings in the body
        $response_header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $response_headers = substr($this->response, 0, $response_header_size);
        // cURL automatically decodes chunked-messages, this means we have to
        // disallow the Laminas\Http\Response to do it again.
        $response_headers = preg_replace("/Transfer-Encoding:\\s*chunked\\r\\n/i", '', $response_headers);
        // cURL can automatically handle content encoding; prevent double-decoding from occurring
        if (isset($this->config['curloptions'][CURLOPT_ENCODING]) && '' === $this->config['curloptions'][CURLOPT_ENCODING]) {
            $response_headers = preg_replace("/Content-Encoding:\\s*gzip\\r\\n/i", '', (string) $response_headers);
        }
        // cURL automatically handles Proxy rewrites, remove the "HTTP/1.0 200 Connection established" string:
        $response_headers = preg_replace("/HTTP\\/1.[01]\\s*200\\s*Connection\\s*established\\r\\n\\r\\n/", '', (string) $response_headers);
        // replace old header with new, cleaned up, header
        $this->response = substr_replace($this->response, $response_headers, 0, $response_header_size);
        // Eliminate multiple HTTP responses.
        do {
            $parts = preg_split('|(?:\r?\n){2}|m', $this->response, 2);
            $again = false;
            if (isset($parts[1]) && preg_match("|^HTTP/1\\.[01](.*?)\r\n|mi", $parts[1])) {
                $this->response = $parts[1];
                $again = true;
            }
        } while ($again);
        return $request;
    }
    /**
     * Return read response from server
     *
     * @return string
     */
    public function read()
    {
        return $this->response;
    }
    /**
     * Close the connection to the server
     */
    public function close(): void
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        $this->curl = null;
        $this->connected_to = [null, null];
    }
    /**
     * Get cUrl Handle
     *
     * @return resource
     */
    public function get_handle()
    {
        return $this->curl;
    }
    /**
     * Set output stream for the response
     *
     * @param resource $stream
     * @return $this
     */
    public function set_output_stream($stream): static
    {
        $this->output_stream = $stream;
        return $this;
    }
    /**
     * Header reader function for CURL
     *
     * @param resource $curl
     */
    public function read_header($curl, string $header): int
    {
        $this->response .= $header;
        return strlen($header);
    }
}