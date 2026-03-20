<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function array_intersect;
use function array_shift;
use function array_walk;
use function count;
use function explode;
use function implode;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function sprintf;
use stdClass;
use function str_split;
use function stripslashes;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function usort;
use function version_compare;
/**
 * Abstract Accept Header
 *
 * Naming conventions:
 *
 *    Accept: audio/mp3; q=0.2; version=0.5, audio/basic+mp3
 *   |------------------------------------------------------|  header line
 *   |------|                                                  field name
 *          |-----------------------------------------------|  field value
 *          |-------------------------------|                  field value part
 *          |------|                                           type
 *                  |--|                                       subtype
 *                  |--|                                       format
 *                                                |----|       subtype
 *                                                      |---|  format
 *                      |-------------------|                  parameter set
 *                              |-----------|                  parameter
 *                              |-----|                        parameter key
 *                                      |--|                   parameter value
 *                        |---|                                priority
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
abstract class Abstract_Accept implements Header_Interface
{
    /** @var stdClass[] */
    protected $field_value_parts = [];
    /** @var string */
    protected $regex_add_type;
    /**
     * Determines if since last mutation the stack was sorted
     *
     * @var bool
     */
    protected $sorted = false;
    /**
     * Parse a full header line or just the field value part.
     *
     * @param string $headerLine
     */
    public function parse_header_line($header_line): void
    {
        if (str_contains($header_line, ':')) {
            [$name, $value] = Generic_Header::split_header_line($header_line);
            if (strtolower($name) !== strtolower($this->get_field_name())) {
                $value = $header_line;
                // This is just for preserve the BC.
            }
        } else {
            $value = $header_line;
        }
        Header_Value::assert_valid($value);
        foreach ($this->get_field_value_parts_from_header_line($value) as $value) {
            $this->add_field_value_part_to_queue($value);
        }
    }
    /**
     * Factory method: parse Accept header string
     *
     * @param  string $headerLine
     * @return static
     */
    public static function from_string($header_line)
    {
        $obj = new static();
        $obj->parse_header_line($header_line);
        return $obj;
    }
    /**
     * Parse the Field Value Parts represented by a header line
     *
     * @param string  $headerLine
     * @throws Exception\InvalidArgumentException If header is invalid.
     * @return array
     */
    public function get_field_value_parts_from_header_line($header_line)
    {
        // process multiple accept values, they may be between quotes
        if (!preg_match_all('/(?:[^,"]|"(?:[^\\\\"]|\\\\.)*")+/', $header_line, $values) || !isset($values[0])) {
            throw new Exception\InvalidArgumentException('Invalid header line for ' . $this->get_field_name() . ' header string');
        }
        $out = [];
        foreach ($values[0] as $value) {
            $value = trim($value);
            $out[] = $this->parse_field_value_part($value);
        }
        return $out;
    }
    /**
     * Parse the accept params belonging to a media range
     *
     * @param string $fieldValuePart
     * @return stdClass
     */
    protected function parse_field_value_part($field_value_part)
    {
        $raw = $subtype_whole = $type = $field_value_part;
        if ($pos = strpos($field_value_part, ';')) {
            $type = substr($field_value_part, 0, $pos);
        }
        $params = $this->get_parameters_from_field_value_part($field_value_part);
        if ($pos = strpos($field_value_part, ';')) {
            $field_value_part = trim(substr($field_value_part, 0, $pos));
        }
        $format = '*';
        $subtype = '*';
        return (object) ['typeString' => trim($field_value_part), 'type' => $type, 'subtype' => $subtype, 'subtypeRaw' => $subtype_whole, 'format' => $format, 'priority' => $params['q'] ?? 1, 'params' => $params, 'raw' => trim($raw)];
    }
    /**
     * Parse the keys contained in the header line
     *
     * @param string $fieldValuePart
     * @return array
     */
    protected function get_parameters_from_field_value_part($field_value_part)
    {
        $params = [];
        if (($pos = strpos($field_value_part, ';')) !== false) {
            preg_match_all('/(?:[^;"]|"(?:[^\\\\"]|\\\\.)*")+/', $field_value_part, $params_strings);
            if (isset($params_strings[0])) {
                array_shift($params_strings[0]);
                $params_strings = $params_strings[0];
            }
            foreach ($params_strings as $param) {
                $explode = explode('=', $param, 2);
                if (count($explode) === 2) {
                    $value = trim($explode[1]);
                } else {
                    $value = null;
                }
                if (isset($value[0]) && $value[0] === '"' && str_ends_with($value, '"')) {
                    $value = substr(substr($value, 1), 0, -1);
                }
                $params[trim($explode[0])] = stripslashes($value ?? '');
            }
        }
        return $params;
    }
    /**
     * Get field value
     *
     * @param array|null $values
     * @return string
     */
    public function get_field_value($values = null)
    {
        if ($values === null) {
            return $this->get_field_value($this->field_value_parts);
        }
        $strings = [];
        foreach ($values as $value) {
            $params = $value->params;
            array_walk($params, $this->assemble_accept_param(...));
            $strings[] = implode(';', [$value->type_string] + $params);
        }
        return implode(', ', $strings);
    }
    /**
     * Assemble and escape the field value parameters based on RFC 2616 section 2.1
     *
     * @todo someone should review this thoroughly
     * @param string $value
     * @return string
     */
    protected function assemble_accept_param(&$value, string $key)
    {
        $separators = ['(', ')', '<', '>', '@', ',', ';', ':', '/', '[', ']', '?', '=', '{', '}', ' ', "\t"];
        $escaped = preg_replace_callback(
            '/[[:cntrl:]"\\\\]/',
            // escape cntrl, ", \
            fn($v) => '\\' . $v[0],
            $value
        );
        if ($escaped === $value && !array_intersect(str_split($value), $separators)) {
            return $key . ($value ? '=' . $value : '');
        }
        return $key . ($value ? '="' . $escaped . '"' : '');
    }
    /**
     * Add a type, with the given priority
     *
     * @param  string $type
     * @param  int|float $priority
     * @param  array (optional) $params
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    protected function add_type($type, $priority = 1, array $params = [])
    {
        if (!preg_match($this->regex_add_type, $type)) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects a valid type; received "%s"', __METHOD__, (string) $type));
        }
        if (!is_int($priority) && !is_float($priority) && !is_numeric($priority) || $priority > 1 || $priority < 0) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects a numeric priority; received %s', __METHOD__, (string) $priority));
        }
        if ($priority !== 1) {
            $params = ['q' => sprintf('%01.1f', $priority)] + $params;
        }
        $assembled_string = $this->get_field_value([(object) ['typeString' => $type, 'params' => $params]]);
        $value = $this->parse_field_value_part($assembled_string);
        $this->add_field_value_part_to_queue($value);
        return $this;
    }
    /**
     * Does the header have the requested type?
     *
     * @param  array|string $matchAgainst
     * @return bool
     */
    protected function has_type($match_against)
    {
        return (bool) $this->match($match_against);
    }
    /**
     * Match a media string against this header
     *
     * @param array|string $matchAgainst
     * @return Accept\FieldValuePart\AcceptFieldValuePart|bool The matched value or false
     */
    public function match($match_against)
    {
        if (is_string($match_against)) {
            $match_against = $this->get_field_value_parts_from_header_line($match_against);
        }
        foreach ($this->get_prioritized() as $left) {
            foreach ($match_against as $right) {
                if ($right->type === '*' || $left->type === '*') {
                    if ($this->match_accept_params($left, $right)) {
                        $left->set_matched_against($right);
                        return $left;
                    }
                }
                if ($left->type === $right->type) {
                    if (($left->subtype === $right->subtype || ($right->subtype === '*' || $left->subtype === '*')) && ($left->format === $right->format || $right->format === '*' || $left->format === '*')) {
                        if ($this->match_accept_params($left, $right)) {
                            $left->set_matched_against($right);
                            return $left;
                        }
                    }
                }
            }
        }
        return false;
    }
    /**
     * Return a match where all parameters in argument #1 match those in argument #2
     *
     * @param array $match1
     * @param array $match2
     * @return bool|array
     */
    protected function match_accept_params($match1, $match2)
    {
        foreach ($match2->params as $key => $value) {
            if (isset($match1->params[$key])) {
                if (strpos((string) $value, '-')) {
                    preg_match('/^(?|([^"-]*)|"([^"]*)")-(?|([^"-]*)|"([^"]*)")\z/', (string) $value, $pieces);
                    if (count($pieces) === 3 && (version_compare($pieces[1], $match1->params[$key], '<=') xor version_compare($pieces[2], $match1->params[$key], '>='))) {
                        return false;
                    }
                } elseif (strpos((string) $value, '|')) {
                    $options = explode('|', (string) $value);
                    $good = false;
                    foreach ($options as $option) {
                        if ($option === $match1->params[$key]) {
                            $good = true;
                            break;
                        }
                    }
                    if (!$good) {
                        return false;
                    }
                } elseif ($match1->params[$key] !== $value) {
                    return false;
                }
            }
        }
        return $match1;
    }
    /**
     * Add a key/value combination to the internal queue
     *
     * @param stdClass $value
     * @return void
     */
    protected function add_field_value_part_to_queue($value)
    {
        $this->field_value_parts[] = $value;
        $this->sorted = false;
    }
    /**
     * Sort the internal Field Value Parts
     *
     * @see rfc2616 sect 14.1
     * Media ranges can be overridden by more specific media ranges or
     * specific media types. If more than one media range applies to a given
     * type, the most specific reference has precedence. For example,
     *
     * Accept: text/*, text/html, text/html;level=1, * /*
     *
     * have the following precedence:
     *
     * 1) text/html;level=1
     * 2) text/html
     * 3) text/*
     * 4) * /*
     *
     * @return void
     */
    protected function sort_field_value_parts()
    {
        $sort = static function (object $a, object $b): int {
            // If A has higher precedence than B, return -1.
            if ($a->priority > $b->priority) {
                return -1;
            }
            // If A has higher precedence than B, return -1.
            if ($a->priority < $b->priority) {
                return 1;
            }
            // Asterisks
            $values = ['type', 'subtype', 'format'];
            foreach ($values as $value) {
                if ($a->{$value} === '*' && $b->{$value} !== '*') {
                    return 1;
                }
                if ($b->{$value} === '*' && $a->{$value} !== '*') {
                    return -1;
                }
            }
            if ($a->type === 'application' && $b->type !== 'application') {
                return -1;
            }
            if ($b->type === 'application' && $a->type !== 'application') {
                return 1;
            }
            return strlen($b->raw) <=> strlen($a->raw);
        };
        usort($this->field_value_parts, $sort);
        $this->sorted = true;
    }
    /**
     * @return array with all the keys, values and parameters this header represents:
     */
    public function get_prioritized()
    {
        if (!$this->sorted) {
            $this->sort_field_value_parts();
        }
        return $this->field_value_parts;
    }
}