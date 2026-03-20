<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use function explode;
use function implode;
use function strpos;
class Generic_Multi_Header extends Generic_Header implements Multiple_Header_Interface
{
    /**
     * @param string $headerLine
     * @return static|static[]
     */
    public static function from_string($header_line): array|self
    {
        [$field_name, $field_value] = Generic_Header::split_header_line($header_line);
        if (strpos($field_value, ',')) {
            $headers = [];
            foreach (explode(',', $field_value) as $multi_value) {
                $headers[] = new static($field_name, $multi_value);
            }
            return $headers;
        }
        return new static($field_name, $field_value);
    }
    public function to_string_multiple_headers(array $headers): string
    {
        $name = $this->get_field_name();
        $values = [$this->get_field_value()];
        foreach ($headers as $header) {
            if (!$header instanceof static) {
                throw new Exception\InvalidArgumentException('This method toStringMultipleHeaders was expecting an array of headers of the same type');
            }
            $values[] = $header->get_field_value();
        }
        return $name . ': ' . implode(',', $values) . "\r\n";
    }
}