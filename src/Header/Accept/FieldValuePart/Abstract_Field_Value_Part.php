<?php

declare (strict_types=1);
namespace Laminas\Http\Header\Accept\Field_Value_Part;

use stdClass;
/**
 * Field Value Part
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
abstract class Abstract_Field_Value_Part
{
    /**
     * A Field Value Part this Field Value Part matched against.
     *
     * @var AbstractFieldValuePart
     */
    protected $matched_against;
    /**
     * @param object $internalValues
     */
    public function __construct(
        /**
         * Internal object used for value retrieval
         */
        private $internal_values
    )
    {
    }
    /**
     * Set a Field Value Part this Field Value Part matched against.
     *
     * @return $this
     */
    public function set_matched_against(Abstract_Field_Value_Part $matched_against)
    {
        $this->matched_against = $matched_against;
        return $this;
    }
    /**
     * Get a Field Value Part this Field Value Part matched against.
     *
     * @return AbstractFieldValuePart|null
     */
    public function get_matched_against()
    {
        return $this->matched_against;
    }
    /**
     * @return object
     */
    protected function get_internal_values()
    {
        return $this->internal_values;
    }
    /**
     * @return string $typeString
     */
    public function get_type_string()
    {
        return $this->get_internal_values()->type_string;
    }
    /**
     * @return float $priority
     */
    public function get_priority()
    {
        return (float) $this->get_internal_values()->priority;
    }
    /**
     * @return stdClass $params
     */
    public function get_params()
    {
        return (object) $this->get_internal_values()->params;
    }
    /**
     * @return string $raw
     */
    public function get_raw()
    {
        return $this->get_internal_values()->raw;
    }
    /**
     * @param mixed $key
     */
    public function __get(string $key): mixed
    {
        return $this->get_internal_values()->{$key};
    }
}