<?php

declare (strict_types=1);
namespace Laminas\Http\Header\Accept\Field_Value_Part;

/**
 * Field Value Part
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
class Charset_Field_Value_Part extends Abstract_Field_Value_Part
{
    /**
     * @return string
     */
    public function get_charset()
    {
        return $this->get_internal_values()->type;
    }
}