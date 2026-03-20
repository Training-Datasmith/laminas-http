<?php

declare (strict_types=1);
namespace Laminas\Http;

use function is_array;
/**
 * Http static client
 */
class Client_Static
{
    /** @var Client */
    protected static $client;
    /**
     * Get the static HTTP client
     *
     * @param array|Traversable $options
     * @return Client
     */
    protected static function get_static_client($options = null)
    {
        if (!isset(static::$client) || $options !== null) {
            static::$client = new Client(null, $options);
        }
        return static::$client;
    }
    /**
     * HTTP GET METHOD (static)
     *
     * @param  string $url
     * @param  array $query
     * @param  array $headers
     * @param  mixed $body
     * @param  array|Traversable $clientOptions
     * @return Response|bool
     */
    public static function get($url, $query = [], $headers = [], $body = null, $client_options = null)
    {
        if (empty($url)) {
            return false;
        }
        $request = new Request();
        $request->set_uri($url);
        $request->set_method(Request::METHOD_GET);
        if (!empty($query) && is_array($query)) {
            $request->get_query()->from_array($query);
        }
        if (!empty($headers) && is_array($headers)) {
            $request->get_headers()->add_headers($headers);
        }
        if (!empty($body)) {
            $request->set_content($body);
        }
        return static::get_static_client($client_options)->send($request);
    }
    /**
     * HTTP POST METHOD (static)
     *
     * @param  string $url
     * @param  array $params
     * @param  array $headers
     * @param  mixed $body
     * @param  array|Traversable $clientOptions
     * @throws Exception\InvalidArgumentException
     * @return Response|bool
     */
    public static function post($url, $params, $headers = [], $body = null, $client_options = null)
    {
        if (empty($url)) {
            return false;
        }
        $request = new Request();
        $request->set_uri($url);
        $request->set_method(Request::METHOD_POST);
        if (!empty($params) && is_array($params)) {
            $request->get_post()->from_array($params);
        } else {
            throw new Exception\InvalidArgumentException('The array of post parameters is empty');
        }
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = Client::ENC_URLENCODED;
        }
        if (!empty($headers) && is_array($headers)) {
            $request->get_headers()->add_headers($headers);
        }
        if (!empty($body)) {
            $request->set_content($body);
        }
        return static::get_static_client($client_options)->send($request);
    }
}