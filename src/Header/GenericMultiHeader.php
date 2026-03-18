<?php

declare(strict_types=1);

namespace Laminas\Http\Header;

use function explode;
use function implode;
use function strpos;

class GenericMultiHeader extends GenericHeader implements MultipleHeaderInterface
{
    /**
     * @param string $headerLine
     * @return static|static[]
     */
    public static function fromString($headerLine): array|self
    {
        [$fieldName, $fieldValue] = GenericHeader::splitHeaderLine($headerLine);

        if (strpos($fieldValue, ',')) {
            $headers = [];
            foreach (explode(',', $fieldValue) as $multiValue) {
                $headers[] = new static($fieldName, $multiValue);
            }
            return $headers;
        }
        return new static($fieldName, $fieldValue);
    }

    public function toStringMultipleHeaders(array $headers): string
    {
        $name   = $this->getFieldName();
        $values = [$this->getFieldValue()];
        foreach ($headers as $header) {
            if (! $header instanceof static) {
                throw new Exception\InvalidArgumentException(
                    'This method toStringMultipleHeaders was expecting an array of headers of the same type'
                );
            }
            $values[] = $header->getFieldValue();
        }
        return $name . ': ' . implode(',', $values) . "\r\n";
    }
}
