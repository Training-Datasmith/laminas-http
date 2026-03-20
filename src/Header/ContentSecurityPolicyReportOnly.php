<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

/**
 * Content Security Policy Level 3 Header
 *
 * @link http://www.w3.org/TR/CSP/
 */
class Content_Security_Policy_Report_Only extends Content_Security_Policy
{
    /**
     * Get the header name
     */
    public function get_field_name(): string
    {
        return 'Content-Security-Policy-Report-Only';
    }
}