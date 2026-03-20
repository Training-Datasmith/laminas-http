<?php

declare (strict_types=1);
namespace Laminas\Http\Header;

use DateTime;
use DateTimeZone;
use Exception;
use function is_numeric;
use function is_string;
use Laminas\Http\Header\Exception\InvalidArgumentException;
use function sprintf;
use function strtolower;
use function strtotime;
/**
 * Abstract Date/Time Header
 * Supports headers that have date/time as value
 *
 * @see Laminas\Http\Header\Date
 * @see Laminas\Http\Header\Expires
 * @see Laminas\Http\Header\IfModifiedSince
 * @see Laminas\Http\Header\IfUnmodifiedSince
 * @see Laminas\Http\Header\LastModified
 *
 * Note for 'Location' header:
 * While RFC 1945 requires an absolute URI, most of the browsers also support relative URI
 * This class allows relative URIs, and let user retrieve URI instance if strict validation needed
 */
abstract class Abstract_Date implements Header_Interface, \Stringable
{
    /**
     * Date formats according to RFC 2616
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3
     */
    public const DATE_RFC1123 = 0;
    public const DATE_RFC1036 = 1;
    public const DATE_ANSIC = 2;
    /**
     * Date instance for this header
     *
     * @var DateTime
     */
    protected $date;
    /**
     * Date output format
     *
     * @var string
     */
    protected static $date_format = 'D, d M Y H:i:s \G\M\T';
    /**
     * Date formats defined by RFC 2616. RFC 1123 date is required
     * RFC 1036 and ANSI C formats are provided for compatibility with old servers/clients
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3
     *
     * @var array
     */
    protected static $date_formats = [self::DATE_RFC1123 => 'D, d M Y H:i:s \G\M\T', self::DATE_RFC1036 => 'D, d M y H:i:s \G\M\T', self::DATE_ANSIC => 'D M j H:i:s Y'];
    /**
     * Create date-based header from string
     *
     * @param string $headerLine
     * @return static
     * @throws InvalidArgumentException
     */
    public static function from_string($header_line)
    {
        $date_header = new static();
        [$name, $date] = Generic_Header::split_header_line($header_line);
        // check to ensure proper header type for this factory
        if (strtolower($name) !== strtolower($date_header->get_field_name())) {
            throw new InvalidArgumentException('Invalid header line for "' . $date_header->get_field_name() . '" header string');
        }
        $date_header->set_date($date);
        return $date_header;
    }
    /**
     * Create date-based header from strtotime()-compatible string
     *
     * @param int|string $time
     * @return static
     * @throws InvalidArgumentException
     */
    public static function from_time_string($time)
    {
        return static::from_timestamp(strtotime((string) $time));
    }
    /**
     * Create date-based header from Unix timestamp
     *
     * @param int $time
     * @return static
     * @throws InvalidArgumentException
     */
    public static function from_timestamp($time)
    {
        $date_header = new static();
        if (!$time || !is_numeric($time)) {
            throw new InvalidArgumentException('Invalid time for "' . $date_header->get_field_name() . '" header string');
        }
        $date_header->set_date(new DateTime('@' . $time));
        return $date_header;
    }
    /**
     * Set date output format
     *
     * @param int $format
     * @throws InvalidArgumentException
     */
    public static function set_date_format($format): void
    {
        if (!isset(static::$date_formats[$format])) {
            throw new InvalidArgumentException(sprintf('No constant defined for provided date format: %s', $format));
        }
        static::$date_format = static::$date_formats[$format];
    }
    /**
     * Return current date output format
     *
     * @return string
     */
    public static function get_date_format()
    {
        return static::$date_format;
    }
    /**
     * Set the date for this header, this can be a string or an instance of \DateTime
     *
     * @param string|DateTime $date
     * @return $this
     * @throws InvalidArgumentException
     */
    public function set_date($date)
    {
        if (is_string($date)) {
            try {
                $date = new DateTime($date, new DateTimeZone('GMT'));
            } catch (Exception $e) {
                throw new InvalidArgumentException(sprintf('Invalid date passed as string (%s)', $date), $e->get_code(), $e);
            }
        } elseif (!$date instanceof DateTime) {
            throw new InvalidArgumentException('Date must be an instance of \DateTime or a string');
        }
        $date->set_timezone(new DateTimeZone('GMT'));
        $this->date = $date;
        return $this;
    }
    /**
     * Return date for this header
     *
     * @return string
     */
    public function get_date()
    {
        return $this->date()->format(static::$date_format);
    }
    /**
     * Return date for this header as an instance of \DateTime
     *
     * @return DateTime
     */
    public function date()
    {
        if ($this->date === null) {
            $this->date = new DateTime('now', new DateTimeZone('GMT'));
        }
        return $this->date;
    }
    /**
     * Compare provided date to date for this header
     * Returns < 0 if date in header is less than $date; > 0 if it's greater, and 0 if they are equal.
     *
     * @see \strcmp()
     *
     * @param string|DateTime $date
     * @return int
     * @throws InvalidArgumentException
     */
    public function compare_to($date)
    {
        if (is_string($date)) {
            try {
                $date = new DateTime($date, new DateTimeZone('GMT'));
            } catch (Exception $e) {
                throw new InvalidArgumentException(sprintf('Invalid Date passed as string (%s)', $date), $e->get_code(), $e);
            }
        } elseif (!$date instanceof DateTime) {
            throw new InvalidArgumentException('Date must be an instance of \DateTime or a string');
        }
        $date_timestamp = $date->get_timestamp();
        $this_timestamp = $this->date()->get_timestamp();
        return $this_timestamp === $date_timestamp ? 0 : ($this_timestamp > $date_timestamp ? 1 : -1);
    }
    /**
     * Get header value as formatted date
     *
     * @return string
     */
    public function get_field_value()
    {
        return $this->get_date();
    }
    /**
     * Return header line
     *
     * @return string
     */
    public function to_string()
    {
        return $this->get_field_name() . ': ' . $this->get_date();
    }
    /**
     * Allow casting to string
     */
    public function __toString(): string
    {
        return $this->to_string();
    }
}