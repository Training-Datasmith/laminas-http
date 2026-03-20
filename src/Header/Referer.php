<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use Laminas\Uri\Http as HttpUri;
/**
 * Content-Location Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.36
 */
class Referer extends Abstract_Location
{
    /**
     * Set the URI/URL for this header
     * according to RFC Referer URI should not have fragment
     *
     * @param  string|HttpUri $uri
     * @return $this
     */
    public function set_uri($uri): static
    {
        parent::set_uri($uri);
        $this->uri->set_fragment(null);
        return $this;
    }
    /**
     * Return header name
     */
    public function get_field_name(): string
    {
        return 'Referer';
    }
}