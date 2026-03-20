<?php

declare (strict_types=1);
namespace Laminas\Http\Client\Adapter;

use function base64_encode;
use function fgets;
use function fwrite;
use function gettype;
use function is_array;
use function is_resource;
use function is_string;
use Laminas\Http\Client;
use Laminas\Http\Client\Adapter\Exception as AdapterException;
use Laminas\Http\Response;
use Laminas\Stdlib\Array_Utils;
use Laminas\Stdlib\Error_Handler;
use Laminas\Uri\Uri;
use function preg_match;
use function rtrim;
use function sprintf;
use function stream_context_set_option;
use function stream_copy_to_stream;
use function strlen;
use function strtolower;
use function substr;
use Traversable;
/**
 * HTTP Proxy-supporting Laminas\Http\Client adapter class, based on the default
 * socket based adapter.
 *
 * Should be used if proxy HTTP access is required. If no proxy is set, will
 * fall back to Laminas\Http\Client\Adapter\Socket behavior. Just like the
 * default Socket adapter, this adapter does not require any special extensions
 * installed.
 */
class Proxy extends Socket
{
    /**
     * Parameters array
     *
     * @var array
     */
    protected $config = ['persistent' => false, 'ssltransport' => 'tls', 'sslcert' => null, 'sslpassphrase' => null, 'sslverifypeer' => true, 'sslcafile' => null, 'sslcapath' => null, 'sslallowselfsigned' => false, 'sslusecontext' => false, 'sslverifypeername' => true, 'proxy_host' => '', 'proxy_port' => 8080, 'proxy_user' => '', 'proxy_pass' => '', 'proxy_auth' => Client::AUTH_BASIC];
    /**
     * Whether HTTPS CONNECT was already negotiated with the proxy or not
     *
     * @var bool
     */
    protected $negotiated = false;
    /**
     * Set the configuration array for the adapter
     *
     * @param array $options
     */
    public function set_options($options = []): void
    {
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        if (!is_array($options)) {
            throw new Adapter_Exception\InvalidArgumentException('Array or Laminas\Config object expected, got ' . gettype($options));
        }
        //enforcing that the proxy keys are set in the form proxy_*
        foreach ($options as $k => $v) {
            if (preg_match('/^proxy[a-z]+/', (string) $k)) {
                $options['proxy_' . substr((string) $k, 5, strlen((string) $k))] = $v;
                unset($options[$k]);
            }
        }
        parent::set_options($options);
    }
    /**
     * Connect to the remote server
     *
     * Will try to connect to the proxy server. If no proxy was set, will
     * fall back to the target server (behave like regular Socket adapter)
     *
     * @param string  $host
     * @param int     $port
     * @param  bool $secure
     * @throws AdapterException\RuntimeException
     */
    public function connect($host, $port = 80, $secure = false): void
    {
        // If no proxy is set, fall back to Socket adapter
        if (!$this->config['proxy_host']) {
            parent::connect($host, $port, $secure);
            return;
        }
        /* Url might require stream context even if proxy connection doesn't */
        if ($secure) {
            $this->config['sslusecontext'] = true;
            $this->set_ssl_crypto_method = false;
        }
        // Connect (a non-secure connection) to the proxy server
        parent::connect($this->config['proxy_host'], $this->config['proxy_port']);
    }
    /**
     * Send request to the proxy server
     *
     * @param string        $method
     * @param Uri $uri
     * @param string        $httpVer
     * @param array         $headers
     * @param string        $body
     * @throws AdapterException\RuntimeException
     * @return string Request as string
     */
    public function write($method, $uri, $http_ver = '1.1', $headers = [], $body = '')
    {
        // If no proxy is set, fall back to default Socket adapter
        if (!$this->config['proxy_host']) {
            return parent::write($method, $uri, $http_ver, $headers, $body);
        }
        // Make sure we're properly connected
        if (!$this->socket) {
            throw new Adapter_Exception\RuntimeException('Trying to write but we are not connected');
        }
        $host = $this->config['proxy_host'];
        $port = $this->config['proxy_port'];
        $is_secure = strtolower((string) $uri->get_scheme()) === 'https';
        $connected_host = ($is_secure ? $this->config['ssltransport'] : 'tcp') . '://' . $host;
        if ($this->connected_to[1] !== $port || $this->connected_to[0] !== $connected_host) {
            throw new Adapter_Exception\RuntimeException('Trying to write but we are connected to the wrong proxy server');
        }
        // Add Proxy-Authorization header
        if ($this->config['proxy_user'] && !isset($headers['proxy-authorization'])) {
            $headers['proxy-authorization'] = Client::encode_auth_header($this->config['proxy_user'], $this->config['proxy_pass'], $this->config['proxy_auth']);
        }
        // if we are proxying HTTPS, preform CONNECT handshake with the proxy
        if ($is_secure && !$this->negotiated) {
            $this->connect_handshake($uri->get_host(), $uri->get_port(), $http_ver, $headers);
            $this->negotiated = true;
        }
        // Save request method for later
        $this->method = $method;
        if ($uri->get_user_info()) {
            $headers['Authorization'] = 'Basic ' . base64_encode((string) $uri->get_user_info());
        }
        $path = $uri->get_path();
        $query = $uri->get_query();
        $path .= $query ? '?' . $query : '';
        if (!$this->negotiated) {
            $path = $uri->get_scheme() . '://' . $uri->get_host() . $path;
        }
        // Build request headers
        $request = sprintf('%s %s HTTP/%s%s', $method, $path, $http_ver, "\r\n");
        // Add all headers to the request string
        foreach ($headers as $k => $v) {
            if (is_string($k)) {
                $v = $k . ': ' . $v;
            }
            $request .= $v . "\r\n";
        }
        if (is_resource($body)) {
            $request .= "\r\n";
        } else {
            // Add the request body
            $request .= "\r\n" . $body;
        }
        // Send the request
        Error_Handler::start();
        $test = fwrite($this->socket, $request);
        $error = Error_Handler::stop();
        if ($test === false) {
            throw new Adapter_Exception\RuntimeException('Error writing request to proxy server', 0, $error);
        }
        if (is_resource($body)) {
            if (stream_copy_to_stream($body, $this->socket) === 0) {
                throw new Adapter_Exception\RuntimeException('Error writing request to server');
            }
        }
        return $request;
    }
    /**
     * Preform handshaking with HTTPS proxy using CONNECT method
     *
     * @param int $port
     * @throws AdapterException\RuntimeException
     */
    protected function connect_handshake(string $host, $port = 443, string $http_ver = '1.1', array &$headers = [])
    {
        $request = 'CONNECT ' . $host . ':' . $port . ' HTTP/' . $http_ver . "\r\n" . 'Host: ' . $host . "\r\n";
        // Add the user-agent header
        if (isset($this->config['useragent'])) {
            $request .= 'User-agent: ' . $this->config['useragent'] . "\r\n";
        }
        // If the proxy-authorization header is set, send it to proxy but remove
        // it from headers sent to target host
        if (isset($headers['proxy-authorization'])) {
            $request .= 'Proxy-authorization: ' . $headers['proxy-authorization'] . "\r\n";
            unset($headers['proxy-authorization']);
        }
        $request .= "\r\n";
        // Send the request
        Error_Handler::start();
        $test = fwrite($this->socket, $request);
        $error = Error_Handler::stop();
        if (!$test) {
            throw new Adapter_Exception\RuntimeException('Error writing request to proxy server', 0, $error);
        }
        // Read response headers only
        $response = '';
        $got_status = false;
        Error_Handler::start();
        while ($line = fgets($this->socket)) {
            $got_status = $got_status || str_contains($line, 'HTTP');
            if ($got_status) {
                $response .= $line;
                if (!rtrim($line)) {
                    break;
                }
            }
        }
        Error_Handler::stop();
        // Check that the response from the proxy is 200
        if (Response::from_string($response)->get_status_code() !== 200) {
            throw new Adapter_Exception\RuntimeException(sprintf('Unable to connect to HTTPS proxy. Server response: %s', $response));
        }
        // provide hostname to ssl for SNI
        $context = $this->get_stream_context();
        stream_context_set_option($context, 'ssl', 'peer_name', $host);
        try {
            $this->enable_crypto_transport($this->config['ssltransport'], $this->socket, $host);
        } catch (Adapter_Exception\RuntimeException $e) {
            throw new Adapter_Exception\RuntimeException('Unable to connect to HTTPS server through proxy: could not negotiate secure connection.', 0, $e);
        }
    }
    /**
     * Close the connection to the server
     */
    public function close(): void
    {
        parent::close();
        $this->negotiated = false;
    }
    /**
     * Destructor: make sure the socket is disconnected
     */
    public function __destruct()
    {
        if ($this->socket) {
            $this->close();
        }
    }
}