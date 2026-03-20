<?php

declare (strict_types=1);
namespace Laminas\Http\Client\Adapter;

use function count;
use function gettype;
use function is_array;
use function is_string;
use Laminas\Http\Response;
use Laminas\Stdlib\Array_Utils;
use Laminas\Uri\Uri;
use function strtolower;
use Traversable;
/**
 * A testing-purposes adapter.
 *
 * Should be used to test all components that rely on Laminas\Http\Client,
 * without actually performing an HTTP request. You should instantiate this
 * object manually, and then set it as the client's adapter. Then, you can
 * set the expected response using the setResponse() method.
 */
class Test implements Adapter_Interface
{
    /**
     * Parameters array
     *
     * @var array
     */
    protected $config = [];
    /**
     * Buffer of responses to be returned by the read() method.  Can be
     * set using setResponse() and addResponse().
     *
     * @var array
     */
    protected $responses = ["HTTP/1.1 400 Bad Request\r\n\r\n"];
    /**
     * Current position in the response buffer
     *
     * @var int
     */
    protected $response_index = 0;
    /**
     * Whether or not the next request will fail with an exception
     *
     * @var bool
     */
    protected $next_request_will_fail = false;
    /**
     * Set the nextRequestWillFail flag
     *
     * @param  bool $flag
     */
    public function set_next_request_will_fail($flag): static
    {
        $this->next_request_will_fail = (bool) $flag;
        return $this;
    }
    /**
     * Set the configuration array for the adapter
     *
     * @param  array|Traversable $options
     * @throws Exception\InvalidArgumentException
     */
    public function set_options($options = []): void
    {
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException('Array or Traversable object expected, got ' . gettype($options));
        }
        foreach ($options as $k => $v) {
            $this->config[strtolower((string) $k)] = $v;
        }
    }
    /**
     * Connect to the remote server
     *
     * @param  string $host
     * @param  int    $port
     * @param  bool   $secure
     * @throws Exception\RuntimeException
     */
    public function connect($host, $port = 80, $secure = false): void
    {
        if ($this->next_request_will_fail) {
            $this->next_request_will_fail = false;
            throw new Exception\RuntimeException('Request failed');
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
     * @return string Request as string
     */
    public function write($method, $uri, $http_ver = '1.1', $headers = [], $body = ''): string
    {
        // Build request headers
        $path = $uri->get_path();
        if (empty($path)) {
            $path = '/';
        }
        $query = $uri->get_query();
        $path .= $query ? '?' . $query : '';
        $request = $method . ' ' . $path . ' HTTP/' . $http_ver . "\r\n";
        foreach ($headers as $k => $v) {
            if (is_string($k)) {
                $v = $k . ': ' . $v;
            }
            $request .= $v . "\r\n";
        }
        // Add the request body
        $request .= "\r\n" . $body;
        // Do nothing - just return the request as string
        return $request;
    }
    /**
     * Return the response set in $this->setResponse()
     *
     * @return string
     */
    public function read()
    {
        if ($this->response_index >= count($this->responses)) {
            $this->response_index = 0;
        }
        return $this->responses[$this->response_index++];
    }
    /**
     * Close the connection (dummy)
     */
    public function close()
    {
    }
    /**
     * Set the HTTP response(s) to be returned by this adapter
     *
     * @param Response|array|string $response
     */
    public function set_response($response): void
    {
        if ($response instanceof Response) {
            $response = $response->to_string();
        }
        $this->responses = (array) $response;
        $this->response_index = 0;
    }
    /**
     * Add another response to the response buffer.
     *
     * @param string|Response $response
     */
    public function add_response($response): void
    {
        if ($response instanceof Response) {
            $response = $response->to_string();
        }
        $this->responses[] = $response;
    }
    /**
     * Sets the position of the response buffer.  Selects which
     * response will be returned on the next call to read().
     *
     * @param int $index
     * @throws Exception\OutOfRangeException
     */
    public function set_response_index($index): void
    {
        if ($index < 0 || $index >= count($this->responses)) {
            throw new Exception\OutOfRangeException('Index out of range of response buffer size');
        }
        $this->response_index = $index;
    }
}