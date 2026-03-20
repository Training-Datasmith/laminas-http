<?php

declare (strict_types=1);
namespace Laminas\Http\Client\Adapter;

use function count;
use function ctype_xdigit;
use function extension_loaded;
use function fclose;
use function feof;
use function fgets;
use function fread;
use function ftell;
use function fwrite;
use function get_resource_type;
use function gettype;
use function hexdec;
use function is_array;
use function is_dir;
use function is_file;
use function is_numeric;
use function is_resource;
use function is_string;
use Laminas\Http\Client\Adapter\Adapter_Interface as HttpAdapter;
use Laminas\Http\Client\Adapter\Exception as AdapterException;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Stdlib\Array_Utils;
use Laminas\Stdlib\Error_Handler;
use Laminas\Uri\Uri;
use function openssl_error_string;
use const PHP_VERSION;
use function rtrim;
use function sprintf;
use function str_ireplace;
use const STREAM_CLIENT_CONNECT;
use const STREAM_CLIENT_PERSISTENT;
use function stream_context_create;
use function stream_context_set_option;
use function stream_copy_to_stream;
use const Stream_crypto_method_ss_Lv23_client;
use const Stream_crypto_method_ss_Lv2_client;
use const Stream_crypto_method_ss_Lv3_client;
use const STREAM_CRYPTO_METHOD_TLS_CLIENT;
use const Stream_crypto_method_tl_Sv1_0_client;
use const Stream_crypto_method_tl_Sv1_1_client;
use const Stream_crypto_method_tl_Sv1_2_client;
use function stream_get_meta_data;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use Traversable;
use function trim;
use function version_compare;
/**
 * A sockets based (stream\socket\client) adapter class for Laminas\Http\Client. Can be used
 * on almost every PHP environment, and does not require any special extensions.
 */
class Socket implements Http_Adapter, Stream_Interface
{
    /**
     * Map SSL transport wrappers to stream crypto method constants
     *
     * @var array
     */
    protected static $ssl_crypto_types = ['ssl' => Stream_crypto_method_ss_Lv23_client, 'sslv2' => Stream_crypto_method_ss_Lv2_client, 'sslv3' => Stream_crypto_method_ss_Lv3_client, 'tls' => STREAM_CRYPTO_METHOD_TLS_CLIENT];
    /**
     * The socket for server connection
     *
     * @var resource|null
     */
    protected $socket;
    /**
     * What host/port are we connected to?
     *
     * @var array
     */
    protected $connected_to = [null, null];
    /**
     * Stream for storing output
     *
     * @var resource
     */
    protected $out_stream;
    /**
     * Parameters array
     *
     * @var array
     */
    protected $config = ['persistent' => false, 'ssltransport' => 'tls', 'sslcert' => null, 'sslpassphrase' => null, 'sslverifypeer' => true, 'sslcafile' => null, 'sslcapath' => null, 'sslallowselfsigned' => false, 'sslusecontext' => false, 'sslverifypeername' => true];
    /**
     * Request method - will be set by write() and might be used by read()
     *
     * @var string
     */
    protected $method;
    /**
     * Stream context
     *
     * @var resource
     */
    protected $context;
    /** @var bool */
    protected $set_ssl_crypto_method = true;
    /**
     * Set the configuration array for the adapter
     *
     * @param  array|Traversable $options
     * @throws AdapterException\InvalidArgumentException
     */
    public function set_options($options = []): void
    {
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        if (!is_array($options)) {
            throw new Adapter_Exception\InvalidArgumentException('Array or Laminas\Config object expected, got ' . gettype($options));
        }
        foreach ($options as $k => $v) {
            $this->config[strtolower((string) $k)] = $v;
        }
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
     * Set the stream context for the TCP connection to the server
     *
     * Can accept either a pre-existing stream context resource, or an array
     * of stream options, similar to the options array passed to the
     * stream_context_create() PHP function. In such case a new stream context
     * will be created using the passed options.
     *
     * @since  Laminas 1.9
     * @param  mixed $context Stream context or array of context options
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function set_stream_context($context): static
    {
        if (is_resource($context) && get_resource_type($context) === 'stream-context') {
            $this->context = $context;
        } elseif (is_array($context)) {
            $this->context = stream_context_create($context);
        } else {
            // Invalid parameter
            throw new Adapter_Exception\InvalidArgumentException(sprintf('Expecting either a stream context resource or array, got %s', gettype($context)));
        }
        return $this;
    }
    /**
     * Get the stream context for the TCP connection to the server.
     *
     * If no stream context is set, will create a default one.
     *
     * @return resource
     */
    public function get_stream_context()
    {
        if (!$this->context) {
            $this->context = stream_context_create();
        }
        return $this->context;
    }
    /**
     * Connect to the remote server
     *
     * @param string  $host
     * @param int     $port
     * @param  bool $secure
     * @throws AdapterException\RuntimeException
     */
    public function connect($host, $port = 80, $secure = false): void
    {
        // If we are connected to the wrong host, disconnect first
        $connected_to = $this->connected_to[0] ?? '';
        $connected_host = strpos($connected_to, '://') ? substr($connected_to, strpos($connected_to, '://') + 3, strlen($connected_to)) : $connected_to;
        if ($connected_host !== $host || $this->connected_to[1] !== $port) {
            if (is_resource($this->socket)) {
                $this->close();
            }
        }
        // Now, if we are not connected, connect
        if (!is_resource($this->socket) || !$this->config['keepalive']) {
            $context = $this->get_stream_context();
            if ($secure || $this->config['sslusecontext']) {
                if ($this->config['sslverifypeer'] !== null) {
                    if (!stream_context_set_option($context, 'ssl', 'verify_peer', $this->config['sslverifypeer'])) {
                        throw new Adapter_Exception\RuntimeException('Unable to set sslverifypeer option');
                    }
                }
                if ($this->config['sslcafile']) {
                    if (!stream_context_set_option($context, 'ssl', 'cafile', $this->config['sslcafile'])) {
                        throw new Adapter_Exception\RuntimeException('Unable to set sslcafile option');
                    }
                }
                if ($this->config['sslcapath']) {
                    if (!stream_context_set_option($context, 'ssl', 'capath', $this->config['sslcapath'])) {
                        throw new Adapter_Exception\RuntimeException('Unable to set sslcapath option');
                    }
                }
                if ($this->config['sslallowselfsigned'] !== null) {
                    if (!stream_context_set_option($context, 'ssl', 'allow_self_signed', $this->config['sslallowselfsigned'])) {
                        throw new Adapter_Exception\RuntimeException('Unable to set sslallowselfsigned option');
                    }
                }
                if ($this->config['sslcert'] !== null) {
                    if (!stream_context_set_option($context, 'ssl', 'local_cert', $this->config['sslcert'])) {
                        throw new Adapter_Exception\RuntimeException('Unable to set sslcert option');
                    }
                }
                if ($this->config['sslpassphrase'] !== null) {
                    if (!stream_context_set_option($context, 'ssl', 'passphrase', $this->config['sslpassphrase'])) {
                        throw new Adapter_Exception\RuntimeException('Unable to set sslpassphrase option');
                    }
                }
                if ($this->config['sslverifypeername'] !== null) {
                    if (!stream_context_set_option($context, 'ssl', 'verify_peer_name', $this->config['sslverifypeername'])) {
                        throw new Adapter_Exception\RuntimeException('Unable to set sslverifypeername option');
                    }
                }
            }
            $flags = STREAM_CLIENT_CONNECT;
            if ($this->config['persistent']) {
                $flags |= STREAM_CLIENT_PERSISTENT;
            }
            if (isset($this->config['connecttimeout'])) {
                $connect_timeout = $this->config['connecttimeout'];
            } else {
                $connect_timeout = $this->config['timeout'];
            }
            if ($connect_timeout !== null && !is_numeric($connect_timeout)) {
                throw new Adapter_Exception\InvalidArgumentException(sprintf('integer or numeric string expected, got %s', gettype($connect_timeout)));
            }
            Error_Handler::start();
            $this->socket = stream_socket_client($host . ':' . $port, $errno, $errstr, (int) $connect_timeout, $flags, $context);
            $error = Error_Handler::stop();
            if (!$this->socket) {
                $this->close();
                throw new Adapter_Exception\RuntimeException(sprintf('Unable to connect to %s:%d%s', $host, $port, $error ? ' . Error #' . $error->get_code() . ': ' . $error->get_message() : ''), 0, $error);
            }
            // Set the stream timeout
            if (!stream_set_timeout($this->socket, (int) $this->config['timeout'])) {
                throw new Adapter_Exception\RuntimeException('Unable to set the connection timeout');
            }
            if ($secure || $this->config['sslusecontext']) {
                if ($this->set_ssl_crypto_method) {
                    try {
                        $this->enable_crypto_transport($this->config['ssltransport'], $this->socket, $host);
                    } catch (Adapter_Exception\RuntimeException $e) {
                        $this->close();
                        throw $e;
                    }
                }
                $host = $this->config['ssltransport'] . '://' . $host;
            } else {
                $host = 'tcp://' . $host;
            }
            // Update connectedTo
            $this->connected_to = [$host, $port];
        }
    }
    /**
     * @param string $sslTransport Transport name from $config['ssltransport']
     * @param resource $socket
     * @param string $host Host name used only for useful exception message
     */
    protected function enable_crypto_transport($ssl_transport, $socket, $host)
    {
        $ssl_crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (isset(static::$ssl_crypto_types[$ssl_transport])) {
            $ssl_crypto_method = static::$ssl_crypto_types[$this->config['ssltransport']];
        }
        // Since php 5.6.7 and up to 7.2.0 constant means tls 1.0 only, expand back to all versions
        // We can do this because STREAM_CRYPTO_METHOD_TLS_ANY_CLIENT is available
        // in enum but not registered as php constant.
        // @see  https://github.com/php/php-src/blob/php-5.6.7/main/streams/php_stream_transport.h#L179
        if (version_compare(PHP_VERSION, '7.2.0', '<') && $ssl_crypto_method === STREAM_CRYPTO_METHOD_TLS_CLIENT) {
            $ssl_crypto_method = Stream_crypto_method_tl_Sv1_0_client;
            $ssl_crypto_method |= Stream_crypto_method_tl_Sv1_1_client;
            $ssl_crypto_method |= Stream_crypto_method_tl_Sv1_2_client;
        }
        Error_Handler::start();
        $test = stream_socket_enable_crypto($socket, true, $ssl_crypto_method);
        $error = Error_Handler::stop();
        if (!$test || $error) {
            // Error handling is kind of difficult when it comes to SSL
            $error_string = '';
            if (extension_loaded('openssl')) {
                while (($ssl_error = openssl_error_string()) !== false) {
                    $error_string .= sprintf('; SSL error: %s', $ssl_error);
                }
            }
            if (!$error_string && $this->config['sslverifypeer']) {
                // There's good chance our error is due to sslcapath not being properly set
                if (!($this->config['sslcafile'] || $this->config['sslcapath'])) {
                    $error_string = 'make sure the "sslcafile" or "sslcapath" option are properly set for ' . 'the environment.';
                } elseif ($this->config['sslcafile'] && !is_file($this->config['sslcafile'])) {
                    $error_string = 'make sure the "sslcafile" option points to a valid SSL certificate ' . 'file';
                } elseif ($this->config['sslcapath'] && !is_dir($this->config['sslcapath'])) {
                    $error_string = 'make sure the "sslcapath" option points to a valid SSL certificate ' . 'directory';
                }
            }
            if ($error_string) {
                $error_string = sprintf(': %s', $error_string);
            }
            throw new Adapter_Exception\RuntimeException(sprintf('Unable to enable crypto on TCP connection %s%s', $host, $error_string), 0, $error);
        }
    }
    /**
     * Send request to the remote server
     *
     * @param string        $method
     * @param Uri $uri
     * @param string        $httpVer
     * @param array         $headers
     * @param string        $body
     * @throws AdapterException\RuntimeException
     * @return string Request as string
     */
    public function write($method, $uri, $http_ver = '1.1', $headers = [], $body = ''): string
    {
        // Make sure we're properly connected
        if (!$this->socket) {
            throw new Adapter_Exception\RuntimeException('Trying to write but we are not connected');
        }
        $host = $uri->get_host();
        $host = (strtolower((string) $uri->get_scheme()) === 'https' ? $this->config['ssltransport'] : 'tcp') . '://' . $host;
        if ($this->connected_to[0] !== $host || $this->connected_to[1] !== $uri->get_port()) {
            throw new Adapter_Exception\RuntimeException('Trying to write but we are connected to the wrong host');
        }
        // Save request method for later
        $this->method = $method;
        // Build request headers
        $path = $uri->get_path();
        $query = $uri->get_query();
        $path .= $query ? '?' . $query : '';
        $request = $method . ' ' . $path . ' HTTP/' . $http_ver . "\r\n";
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
        if (false === $test) {
            throw new Adapter_Exception\RuntimeException('Error writing request to server', 0, $error);
        }
        if (is_resource($body)) {
            if (stream_copy_to_stream($body, $this->socket) === 0) {
                throw new Adapter_Exception\RuntimeException('Error writing request to server');
            }
        }
        return $request;
    }
    /**
     * Read response from server
     *
     * @throws AdapterException\RuntimeException
     * @return string
     */
    public function read()
    {
        // First, read headers only
        $response = '';
        $got_status = false;
        while (($line = fgets($this->socket)) !== false) {
            $got_status = $got_status || str_contains($line, 'HTTP');
            if ($got_status) {
                $response .= $line;
                if (rtrim($line) === '') {
                    break;
                }
            }
        }
        $this->_check_socket_read_timeout();
        $response_obj = Response::from_string($response);
        $status_code = $response_obj->get_status_code();
        // Handle 100 and 101 responses internally by restarting the read again
        if ($status_code === 100 || $status_code === 101) {
            return $this->read();
        }
        // Check headers to see what kind of connection / transfer encoding we have
        $headers = $response_obj->get_headers();
        /**
         * Responses to HEAD requests and 204 or 304 responses are not expected
         * to have a body - stop reading here
         */
        if ($status_code === 304 || $status_code === 204 || $this->method === Request::METHOD_HEAD) {
            // Close the connection if requested to do so by the server
            $connection = $headers->get('connection');
            if ($connection && $connection->get_field_value() === 'close') {
                $this->close();
            }
            return $response;
        }
        // If we got a 'transfer-encoding: chunked' header
        $transfer_encoding = $headers->get('transfer-encoding');
        $content_length = $headers->get('content-length');
        if ($transfer_encoding !== false) {
            if (strtolower($transfer_encoding->get_field_value()) === 'chunked') {
                do {
                    $line = fgets($this->socket);
                    $this->_check_socket_read_timeout();
                    $chunk = $line;
                    // Figure out the next chunk size
                    $chunksize = trim($line);
                    if (!ctype_xdigit($chunksize)) {
                        $this->close();
                        throw new Adapter_Exception\RuntimeException(sprintf('Invalid chunk size "%s" unable to read chunked body', $chunksize));
                    }
                    // Convert the hexadecimal value to plain integer
                    $chunksize = hexdec($chunksize);
                    // Read next chunk
                    $read_to = ftell($this->socket) + $chunksize;
                    do {
                        $current_pos = ftell($this->socket);
                        if ($current_pos >= $read_to) {
                            break;
                        }
                        if ($this->out_stream) {
                            if (stream_copy_to_stream($this->socket, $this->out_stream, $read_to - $current_pos) === 0) {
                                $this->_check_socket_read_timeout();
                                break;
                            }
                        } else {
                            $line = fread($this->socket, $read_to - $current_pos);
                            if ($line === false || strlen($line) === 0) {
                                $this->_check_socket_read_timeout();
                                break;
                            }
                            $chunk .= $line;
                        }
                    } while (!feof($this->socket));
                    Error_Handler::start();
                    $chunk .= fgets($this->socket);
                    Error_Handler::stop();
                    $this->_check_socket_read_timeout();
                    if (!$this->out_stream) {
                        $response .= $chunk;
                    }
                } while ($chunksize > 0);
            } else {
                $this->close();
                throw new Adapter_Exception\RuntimeException(sprintf('Cannot handle "%s" transfer encoding', $transfer_encoding->get_field_value()));
            }
            // We automatically decode chunked-messages when writing to a stream
            // this means we have to disallow the Laminas\Http\Response to do it again
            if ($this->out_stream) {
                $response = str_ireplace("Transfer-Encoding: chunked\r\n", '', $response);
            }
            // Else, if we got the content-length header, read this number of bytes
        } elseif ($content_length !== false) {
            // If we got more than one Content-Length header (see Laminas-9404) use
            // the last value sent
            if (is_array($content_length)) {
                $content_length = $content_length[count($content_length) - 1];
            }
            $content_length = $content_length->get_field_value();
            $current_pos = ftell($this->socket);
            for ($read_to = $current_pos + $content_length; $read_to > $current_pos; $current_pos = ftell($this->socket)) {
                if ($this->out_stream) {
                    if (stream_copy_to_stream($this->socket, $this->out_stream, $read_to - $current_pos) === 0) {
                        $this->_check_socket_read_timeout();
                        break;
                    }
                } else {
                    $chunk = fread($this->socket, $read_to - $current_pos);
                    if ($chunk === false || strlen($chunk) === 0) {
                        $this->_check_socket_read_timeout();
                        break;
                    }
                    $response .= $chunk;
                }
                // Break if the connection ended prematurely
                if (feof($this->socket)) {
                    break;
                }
            }
            // Fallback: just read the response until EOF
        } else {
            do {
                if ($this->out_stream) {
                    if (stream_copy_to_stream($this->socket, $this->out_stream) === 0) {
                        $this->_check_socket_read_timeout();
                        break;
                    }
                } else {
                    $buff = fread($this->socket, 8192);
                    if ($buff === false || strlen($buff) === 0) {
                        $this->_check_socket_read_timeout();
                        break;
                    } else {
                        $response .= $buff;
                    }
                }
            } while (feof($this->socket) === false);
            $this->close();
        }
        // Close the connection if requested to do so by the server
        $connection = $headers->get('connection');
        if ($connection && $connection->get_field_value() === 'close') {
            $this->close();
        }
        return $response;
    }
    /**
     * Close the connection to the server
     */
    public function close(): void
    {
        if (is_resource($this->socket)) {
            Error_Handler::start();
            fclose($this->socket);
            Error_Handler::stop();
        }
        $this->socket = null;
        $this->connected_to = [null, null];
    }
    /**
     * Check if the socket has timed out - if so close connection and throw
     * an exception
     *
     * @throws AdapterException\TimeoutException with READ_TIMEOUT code
     */
    // @codingStandardsIgnoreStart
    protected function _check_socket_read_timeout()
    {
        // @codingStandardsIgnoreEnd
        if ($this->socket) {
            $info = stream_get_meta_data($this->socket);
            $timedout = $info['timed_out'];
            if ($timedout) {
                $this->close();
                throw new Adapter_Exception\Timeout_Exception(sprintf('Read timed out after %d seconds', $this->config['timeout']), Adapter_Exception\Timeout_Exception::READ_TIMEOUT);
            }
        }
    }
    /**
     * Set output stream for the response
     *
     * @param resource $stream
     * @return Socket
     */
    public function set_output_stream($stream): static
    {
        $this->out_stream = $stream;
        return $this;
    }
    /**
     * Destructor: make sure the socket is disconnected
     *
     * If we are in persistent TCP mode, will not close the connection
     */
    public function __destruct()
    {
        if (!$this->config['persistent']) {
            if ($this->socket) {
                $this->close();
            }
        }
    }
}