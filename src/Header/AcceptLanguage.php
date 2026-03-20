<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use Laminas\Http\Header\Accept\Field_Value_Part;
use function strpos;
use function substr;
use function trim;
/**
 * Accept Language Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
 */
class Accept_Language extends Abstract_Accept
{
    /** @var string */
    protected $regex_add_type = '#^([a-zA-Z0-9+-]+|\*)$#';
    /**
     * Get field name
     */
    public function get_field_name(): string
    {
        return 'Accept-Language';
    }
    /**
     * Cast to string
     */
    public function to_string(): string
    {
        return 'Accept-Language: ' . $this->get_field_value();
    }
    /**
     * Add a language, with the given priority
     *
     * @param  string $type
     * @param  int|float $priority
     * @return $this
     */
    public function add_language($type, $priority = 1)
    {
        return $this->add_type($type, $priority);
    }
    /**
     * Does the header have the requested language?
     *
     * @param  string $type
     * @return bool
     */
    public function has_language($type)
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
    protected function parse_field_value_part($field_value_part): \Laminas\Http\Header\Accept\Field_Value_Part\Language_Field_Value_Part
    {
        $raw = $field_value_part;
        if ($pos = strpos($field_value_part, '-')) {
            $type = trim(substr($field_value_part, 0, $pos));
        } else {
            $type = trim(substr($field_value_part, 0));
        }
        $params = $this->get_parameters_from_field_value_part($field_value_part);
        if ($pos = strpos($field_value_part, ';')) {
            $field_value_part = $type = trim(substr($field_value_part, 0, $pos));
        }
        if (strpos($field_value_part, '-')) {
            $subtype_whole = $format = $subtype = trim(substr($field_value_part, strpos($field_value_part, '-') + 1));
        } else {
            $subtype_whole = '';
            $format = '*';
            $subtype = '*';
        }
        $aggregated = ['typeString' => trim($field_value_part), 'type' => $type, 'subtype' => $subtype, 'subtypeRaw' => $subtype_whole, 'format' => $format, 'priority' => $params['q'] ?? 1, 'params' => $params, 'raw' => trim($raw)];
        return new Field_Value_Part\Language_Field_Value_Part((object) $aggregated);
    }
}