<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use Laminas\Http\Header\Accept\Field_Value_Part;
/**
 * Accept Encoding Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.3
 */
class Accept_Encoding extends Abstract_Accept
{
    /** @var string */
    protected $regex_add_type = '#^([a-zA-Z0-9+-]+|\*)$#';
    /**
     * Get field name
     */
    public function get_field_name(): string
    {
        return 'Accept-Encoding';
    }
    /**
     * Cast to string
     */
    public function to_string(): string
    {
        return 'Accept-Encoding: ' . $this->get_field_value();
    }
    /**
     * Add an encoding, with the given priority
     *
     * @param  string $type
     * @param  int|float $priority
     * @return $this
     */
    public function add_encoding($type, $priority = 1)
    {
        return $this->add_type($type, $priority);
    }
    /**
     * Does the header have the requested encoding?
     *
     * @param  string $type
     * @return bool
     */
    public function has_encoding($type)
    {
        return $this->has_type($type);
    }
    /**
     * Parse the keys contained in the header line
     *
     * @see \Laminas\Http\Header\AbstractAccept::parseFieldValuePart()
     *
     * @param string $fieldValuePart
     */
    protected function parse_field_value_part($field_value_part): \Laminas\Http\Header\Accept\Field_Value_Part\Encoding_Field_Value_Part
    {
        $internal_values = parent::parse_field_value_part($field_value_part);
        return new Field_Value_Part\Encoding_Field_Value_Part($internal_values);
    }
}