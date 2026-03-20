<?php

declare(strict_types=1);

/**
 * Example: Making a basic HTTP GET request with laminas-http.
 *
 * Demonstrates creating a client, configuring a request, and
 * reading the response status and body.
 *
 * Run standalone (requires internet access):
 *   php examples/basic_http_request.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Http\Client;
use Laminas\Http\Request;

$client = new Client('https://httpbin.org/get', [
    'timeout' => 10,
]);

$client->set_method(Request::METHOD_GET);
$client->get_request()->get_headers()->add_header_line('Accept: application/json');

$response = $client->send();

echo "Status : " . $response->get_status_code() . " " . $response->get_reason_phrase() . PHP_EOL;
echo "Body   : " . substr($response->get_body(), 0, 200) . PHP_EOL;
