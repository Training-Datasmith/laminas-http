<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use Laminas\Http\Header\Accept\Field_Value_Part;
use function strpos;
use function substr;
use function trim;
/**
 * Accept Header
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
class Accept extends Abstract_Accept
{
    /** @var string */
    protected $regex_add_type = '#^([a-zA-Z+-]+|\*)/(\*|[a-zA-Z0-9+-]+)$#';
    /**
     * Get field name
     */
    public function get_field_name(): string
    {
        return 'Accept';
    }
    /**
     * Cast to string
     */
    public function to_string(): string
    {
        return 'Accept: ' . $this->get_field_value();
    }
    /**
     * Add a media type, with the given priority
     *
     * @param  string $type
     * @param  int|float $priority
     * @return $this
     */
    public function add_media_type($type, $priority = 1, array $params = [])
    {
        return $this->add_type($type, $priority, $params);
    }
    /**
     * Does the header have the requested media type?
     *
     * @param  string $type
     * @return bool
     */
    public function has_media_type($type)
    {
        return $this->has_type($type);
    }
    /**
     * Parse the keys contained in the header line
     *
     * @see    \Laminas\Http\Header\AbstractAccept::parseFieldValuePart()
     *
     * @param  string $fieldValuePart
     */
    protected function parse_field_value_part($field_value_part): \Laminas\Http\Header\Accept\Field_Value_Part\Accept_Field_Value_Part
    {
        $raw = $field_value_part;
        if ($pos = strpos($field_value_part, '/')) {
            $type = trim(substr($field_value_part, 0, $pos));
        } else {
            $type = trim($field_value_part);
        }
        $params = $this->get_parameters_from_field_value_part($field_value_part);
        if ($pos = strpos($field_value_part, ';')) {
            $field_value_part = trim(substr($field_value_part, 0, $pos));
        }
        if (strpos($field_value_part, '/')) {
            $subtype_whole = $format = $subtype = trim(substr($field_value_part, strpos($field_value_part, '/') + 1));
        } else {
            $subtype_whole = '';
            $format = '*';
            $subtype = '*';
        }
        $pos = strpos($subtype, '+');
        if (false !== $pos) {
            $format = trim(substr($subtype, $pos + 1));
            $subtype = trim(substr($subtype, 0, $pos));
        }
        $aggregated = ['typeString' => trim($field_value_part), 'type' => $type, 'subtype' => $subtype, 'subtypeRaw' => $subtype_whole, 'format' => $format, 'priority' => $params['q'] ?? 1, 'params' => $params, 'raw' => trim($raw)];
        return new Field_Value_Part\Accept_Field_Value_Part((object) $aggregated);
    }
}