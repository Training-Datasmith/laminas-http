<?php

declare(strict_types=1);

/**
 * Example: Demonstrating header value validation / CRLF injection prevention.
 *
 * laminas-http's Header_Value class rejects or strips characters that could
 * be used for HTTP response splitting attacks. This example shows both
 * the filtering (best-effort sanitisation) and validation (strict check) APIs.
 *
 * Run standalone:
 *   php examples/header_injection_prevention.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Http\Header\Exception\InvalidArgumentException;
use Laminas\Http\Header\Header_Value;

// --- Scenario 1: attacker injects a second header via CRLF ---
$malicious_location = "https://example.com\r\nX-Injected: evil-value";

// filter() silently strips the injection characters
$safe = Header_Value::filter($malicious_location);
echo "Filtered: " . $safe . PHP_EOL;
// Output: Filtered: https://example.comX-Injected: evil-value

// is_valid() returns false — you can branch on this
if (!Header_Value::is_valid($malicious_location)) {
    echo "Validation: rejected malicious value" . PHP_EOL;
}

// --- Scenario 2: assert_valid() throws for strict enforcement ---
try {
    Header_Value::assert_valid($malicious_location);
} catch (InvalidArgumentException $e) {
    echo "Exception: " . $e->getMessage() . PHP_EOL;
    // Output: Exception: Invalid header value
}

// --- Scenario 3: a safe value passes validation ---
$safe_content_type = 'application/json; charset=utf-8';
Header_Value::assert_valid($safe_content_type); // no exception
echo "Safe value accepted: " . $safe_content_type . PHP_EOL;
