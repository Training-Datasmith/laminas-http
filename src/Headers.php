<?php

declare (strict_types=1);
namespace Laminas\Http;

use function array_keys;
use function array_search;
use function array_shift;
use ArrayIterator;
use function class_implements;
use function count;
use Countable;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use function current;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use Iterator;
use function key;
use Laminas\Http\Header\Exception;
use Laminas\Http\Header\Generic_Header;
use Laminas\Http\Header\Multiple_Header_Interface;
use Laminas\Loader\Plugin_Class_Locator;
use function next;
use function preg_match;
use function reset;
use Return_Type_Will_Change;
use function sprintf;
use function str_replace;
use function strtolower;
use Traversable;
use function trim;
/**
 * Basic HTTP headers collection functionality
 * Handles aggregation of headers
 *
 * @see        http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
 */
class Headers implements Countable, Iterator
{
    /** @var PluginClassLocator */
    protected $plugin_class_loader;
    /** @var array key names for $headers array */
    protected $headers_keys = [];
    /** @var array Array of header array information or Header instances */
    protected $headers = [];
    /**
     * Populates headers from string representation
     *
     * Parses a string for headers, and aggregates them, in order, in the
     * current instance, primarily as strings until they are needed (they
     * will be lazy loaded)
     *
     * @param  string $string
     * @throws Exception\RuntimeException
     */
    public static function from_string($string): static
    {
        $headers = new static();
        $current = [];
        $empty_line = 0;
        // iterate the header lines, some might be continuations
        foreach (explode("\r\n", $string) as $line) {
            // CRLF*2 is end of headers; an empty line by itself or between header lines
            // is an attempt at CRLF injection.
            if (preg_match('/^\s*$/', $line)) {
                // empty line indicates end of headers
                $empty_line += 1;
                if ($empty_line > 2) {
                    throw new Exception\RuntimeException('Malformed header detected');
                }
                continue;
            }
            if ($empty_line) {
                throw new Exception\RuntimeException('Malformed header detected');
            }
            // check if a header name is present
            if (preg_match('/^(?P<name>[^()><@,;:\"\/\[\]?={} \t]+):.*$/', $line, $matches)) {
                if ($current) {
                    // a header name was present, then store the current complete line
                    $headers->headers_keys[] = static::create_key($current['name']);
                    $headers->headers[] = $current;
                }
                $current = ['name' => $matches['name'], 'line' => trim($line)];
                continue;
            }
            if (preg_match("/^[ \t][^\r\n]*\$/", $line, $matches)) {
                // continuation: append to current line
                $current['line'] .= trim($line);
                continue;
            }
            // Line does not match header format!
            throw new Exception\RuntimeException(sprintf('Line "%s" does not match header format!', $line));
        }
        if ($current) {
            $headers->headers_keys[] = static::create_key($current['name']);
            $headers->headers[] = $current;
        }
        return $headers;
    }
    /**
     * Set an alternate implementation for the PluginClassLoader
     *
     * @return $this
     */
    public function set_plugin_class_loader(Plugin_Class_Locator $plugin_class_loader): static
    {
        $this->plugin_class_loader = $plugin_class_loader;
        return $this;
    }
    /**
     * Return an instance of a PluginClassLocator, lazyload and inject map if necessary
     *
     * @return PluginClassLocator
     */
    public function get_plugin_class_loader()
    {
        if ($this->plugin_class_loader === null) {
            $this->plugin_class_loader = new Header_Loader();
        }
        return $this->plugin_class_loader;
    }
    /**
     * Add many headers at once
     *
     * Expects an array (or Traversable object) of type/value pairs.
     *
     * @param  array|Traversable $headers
     * @return $this
     * @throws Exception\InvalidArgumentException
     */
    public function add_headers($headers): static
    {
        if (!is_array($headers) && !$headers instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf('Expected array or Traversable; received "%s"', get_debug_type($headers)));
        }
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                if (is_string($value)) {
                    $this->add_header_line($value);
                } elseif (is_array($value) && count($value) === 1) {
                    $this->add_header_line(key($value), current($value));
                } elseif (is_array($value) && count($value) === 2) {
                    $this->add_header_line($value[0], $value[1]);
                } elseif ($value instanceof Header\Header_Interface) {
                    $this->add_header($value);
                }
            } elseif (is_string($name)) {
                $this->add_header_line($name, $value);
            }
        }
        return $this;
    }
    /**
     * Add a raw header line, either in name => value, or as a single string 'name: value'
     *
     * This method allows for lazy-loading in that the parsing and instantiation of Header object
     * will be delayed until they are retrieved by either get() or current()
     *
     * @throws Exception\InvalidArgumentException
     * @param string $fieldValue optional
     * @return $this
     */
    public function add_header_line(string $header_field_name_or_line, $field_value = null): static
    {
        $matches = null;
        if (preg_match('/^(?P<name>[^()><@,;:\"\/\[\]?=}{ \t]+):.*$/', $header_field_name_or_line, $matches) && $field_value === null) {
            // is a header
            $header_name = $matches['name'];
            $header_key = static::create_key($matches['name']);
            $line = $header_field_name_or_line;
        } elseif ($field_value === null) {
            throw new Exception\InvalidArgumentException('A field name was provided without a field value');
        } else {
            $header_name = $header_field_name_or_line;
            $header_key = static::create_key($header_field_name_or_line);
            if (is_array($field_value)) {
                $field_value = implode('; ', $field_value);
            }
            $line = $header_field_name_or_line . ': ' . $field_value;
        }
        $this->headers_keys[] = $header_key;
        $this->headers[] = ['name' => $header_name, 'line' => $line];
        return $this;
    }
    /**
     * Add a Header to this container, for raw values @see addHeaderLine() and addHeaders()
     *
     * @return $this
     */
    public function add_header(Header\Header_Interface $header): static
    {
        $key = static::create_key($header->get_field_name());
        $index = array_search($key, $this->headers_keys);
        // No header by that key presently; append key and header to list.
        if ($index === false) {
            $this->headers_keys[] = $key;
            $this->headers[] = $header;
            return $this;
        }
        // Header exists, and is a multi-value header; append key and header to
        // list (as multi-value headers are aggregated on retrieval)
        $class = $this->get_plugin_class_loader()->load(str_replace('-', '', $key)) ?: Generic_Header::class;
        if (in_array(Multiple_Header_Interface::class, class_implements($class, true))) {
            $this->headers_keys[] = $key;
            $this->headers[] = $header;
            return $this;
        }
        // Otherwise, we replace the current instance.
        $this->headers[$index] = $header;
        return $this;
    }
    /**
     * Remove a Header from the container
     */
    public function remove_header(Header\Header_Interface $header): bool
    {
        $index = array_search($header, $this->headers, true);
        if ($index !== false) {
            unset($this->headers_keys[$index]);
            unset($this->headers[$index]);
            return true;
        }
        return false;
    }
    /**
     * Clear all headers
     *
     * Removes all headers from queue
     *
     * @return $this
     */
    public function clear_headers(): static
    {
        $this->headers = $this->headers_keys = [];
        return $this;
    }
    /**
     * Get all headers of a certain name/type
     *
     * @param  string $name
     * @return bool|Header\HeaderInterface|ArrayIterator
     */
    public function get($name)
    {
        $key = static::create_key($name);
        if (!$this->has($name)) {
            return false;
        }
        $class = $this->get_plugin_class_loader()->load(str_replace('-', '', $key)) ?: Generic_Header::class;
        if (in_array(Multiple_Header_Interface::class, class_implements($class, true))) {
            $headers = [];
            foreach (array_keys($this->headers_keys, $key) as $index) {
                if (is_array($this->headers[$index])) {
                    $this->lazy_load_header($index);
                }
            }
            foreach (array_keys($this->headers_keys, $key) as $index) {
                $headers[] = $this->headers[$index];
            }
            return new ArrayIterator($headers);
        }
        $index = array_search($key, $this->headers_keys);
        if ($index === false) {
            return false;
        }
        if (is_array($this->headers[$index])) {
            return $this->lazy_load_header($index);
        }
        return $this->headers[$index];
    }
    /**
     * Test for existence of a type of header
     *
     * @param  string $name
     */
    public function has($name): bool
    {
        return in_array(static::create_key($name), $this->headers_keys);
    }
    /**
     * Advance the pointer for this object as an iterator
     */
    #[Return_Type_Will_Change]
    public function next(): void
    {
        next($this->headers);
    }
    /**
     * Return the current key for this object as an iterator
     *
     * @return mixed
     */
    #[Return_Type_Will_Change]
    public function key()
    {
        return key($this->headers);
    }
    /**
     * Is this iterator still valid?
     *
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function valid()
    {
        return current($this->headers) !== false;
    }
    /**
     * Reset the internal pointer for this object as an iterator
     */
    #[Return_Type_Will_Change]
    public function rewind(): void
    {
        reset($this->headers);
    }
    /**
     * Return the current value for this iterator, lazy loading it if need be
     *
     * @return array|Header\HeaderInterface
     */
    #[Return_Type_Will_Change]
    public function current()
    {
        $current = current($this->headers);
        if (is_array($current)) {
            return $this->lazy_load_header(key($this->headers));
        }
        return $current;
    }
    /**
     * Return the number of headers in this contain, if all headers have not been parsed, actual count could
     * increase if MultipleHeader objects exist in the Request/Response.  If you need an exact count, iterate
     *
     * @return int count of currently known headers
     */
    #[Return_Type_Will_Change]
    public function count()
    {
        return count($this->headers);
    }
    /**
     * Render all headers at once
     *
     * This method handles the normal iteration of headers; it is up to the
     * concrete classes to prepend with the appropriate status/request line.
     */
    public function to_string(): string
    {
        $headers = '';
        foreach ($this->to_array() as $field_name => $field_value) {
            if (is_array($field_value)) {
                // Handle multi-value headers
                foreach ($field_value as $value) {
                    $headers .= $field_name . ': ' . $value . "\r\n";
                }
                continue;
            }
            // Handle single-value headers
            $headers .= $field_name . ': ' . $field_value . "\r\n";
        }
        return $headers;
    }
    /**
     * Return the headers container as an array
     *
     * @todo determine how to produce single line headers, if they are supported
     */
    public function to_array(): array
    {
        if ($this->headers === []) {
            return [];
        }
        $this->force_loading();
        $headers = [];
        /** @var Header\HeaderInterface $header */
        foreach ($this->headers as $header) {
            if ($header instanceof Header\Multiple_Header_Interface) {
                $name = $header->get_field_name();
                if (!isset($headers[$name])) {
                    $headers[$name] = [];
                }
                $headers[$name][] = $header->get_field_value();
            } else {
                $headers[$header->get_field_name()] = $header->get_field_value();
            }
        }
        return $headers;
    }
    /**
     * By calling this, it will force parsing and loading of all headers, after this count() will be accurate
     */
    public function force_loading(): bool
    {
        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedForeach
        foreach ($this as $item) {
            // $item should now be loaded
        }
        return true;
    }
    /**
     * @param int|string $index
     * @param bool $isGeneric If true, there is no need to parse $index and call the ClassLoader.
     * @return mixed|void
     */
    protected function lazy_load_header($index, $is_generic = false)
    {
        $current = $this->headers[$index];
        $key = $this->headers_keys[$index];
        /** @var Header\HeaderInterface $class */
        $class = $this->get_plugin_class_loader()->load(str_replace('-', '', $key));
        if ($is_generic || !$class) {
            $class = Generic_Header::class;
        }
        try {
            $headers = $class::from_string($current['line']);
        } catch (Exception\InvalidArgumentException $exception) {
            // Generic Header should throw an exception if it fails
            if ($is_generic) {
                throw $exception;
            }
            // Retry one more time with GenericHeader
            return $this->lazy_load_header($index, true);
        }
        if (is_array($headers)) {
            $this->headers[$index] = $current = array_shift($headers);
            foreach ($headers as $header) {
                $this->headers_keys[] = $key;
                $this->headers[] = $header;
            }
            return $current;
        }
        $this->headers[$index] = $headers;
        return $headers;
    }
    /**
     * Create array key from header name
     *
     * @param string $name
     */
    protected static function create_key($name): string
    {
        return str_replace(['_', ' ', '.'], '-', strtolower($name));
    }
}