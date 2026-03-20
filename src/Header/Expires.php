<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function date;
use const DATE_W3C;
/**
 * Expires Header
 *
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.21
 */
class Expires extends Abstract_Date
{
    /**
     * Get header name
     */
    public function get_field_name(): string
    {
        return 'Expires';
    }
    /**
     * @param int|string|DateTime $date
     * @return static
     */
    public function set_date($date)
    {
        if ($date === '0' || $date === 0) {
            $date = date(DATE_W3C, 0);
            // Thu, 01 Jan 1970 00:00:00 GMT
        }
        return parent::set_date($date);
    }
}