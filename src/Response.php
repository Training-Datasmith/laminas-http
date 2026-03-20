<?php

declare (strict_types=1);
namespace Laminas\Http;

use function array_shift;
use function count;
use function explode;
use function function_exists;
use function gettype;
use function gzdecode;
use function gzinflate;
use function gzuncompress;
use function hexdec;
use function implode;
use function in_array;
use function is_float;
use function is_numeric;
use function is_scalar;
use Laminas\Stdlib\Error_Handler;
use Laminas\Stdlib\Response_Interface;
use function ord;
use function preg_match;
use function sprintf;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function unpack;
/**
 * HTTP Response
 *
 * @link      http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6
 */
class Response extends Abstract_Message implements Response_Interface
{
    /**#@+
     *
     * @const int Status codes
     */
    public const STATUS_CODE_CUSTOM = 0;
    public const STATUS_CODE_100 = 100;
    public const STATUS_CODE_101 = 101;
    public const STATUS_CODE_102 = 102;
    public const STATUS_CODE_200 = 200;
    public const STATUS_CODE_201 = 201;
    public const STATUS_CODE_202 = 202;
    public const STATUS_CODE_203 = 203;
    public const STATUS_CODE_204 = 204;
    public const STATUS_CODE_205 = 205;
    public const STATUS_CODE_206 = 206;
    public const STATUS_CODE_207 = 207;
    public const STATUS_CODE_208 = 208;
    public const STATUS_CODE_226 = 226;
    public const STATUS_CODE_300 = 300;
    public const STATUS_CODE_301 = 301;
    public const STATUS_CODE_302 = 302;
    public const STATUS_CODE_303 = 303;
    public const STATUS_CODE_304 = 304;
    public const STATUS_CODE_305 = 305;
    public const STATUS_CODE_306 = 306;
    public const STATUS_CODE_307 = 307;
    public const STATUS_CODE_308 = 308;
    public const STATUS_CODE_400 = 400;
    public const STATUS_CODE_401 = 401;
    public const STATUS_CODE_402 = 402;
    public const STATUS_CODE_403 = 403;
    public const STATUS_CODE_404 = 404;
    public const STATUS_CODE_405 = 405;
    public const STATUS_CODE_406 = 406;
    public const STATUS_CODE_407 = 407;
    public const STATUS_CODE_408 = 408;
    public const STATUS_CODE_409 = 409;
    public const STATUS_CODE_410 = 410;
    public const STATUS_CODE_411 = 411;
    public const STATUS_CODE_412 = 412;
    public const STATUS_CODE_413 = 413;
    public const STATUS_CODE_414 = 414;
    public const STATUS_CODE_415 = 415;
    public const STATUS_CODE_416 = 416;
    public const STATUS_CODE_417 = 417;
    public const STATUS_CODE_418 = 418;
    public const STATUS_CODE_422 = 422;
    public const STATUS_CODE_423 = 423;
    public const STATUS_CODE_424 = 424;
    public const STATUS_CODE_425 = 425;
    public const STATUS_CODE_426 = 426;
    public const STATUS_CODE_428 = 428;
    public const STATUS_CODE_429 = 429;
    public const STATUS_CODE_431 = 431;
    public const STATUS_CODE_451 = 451;
    public const STATUS_CODE_444 = 444;
    public const STATUS_CODE_499 = 499;
    public const STATUS_CODE_500 = 500;
    public const STATUS_CODE_501 = 501;
    public const STATUS_CODE_502 = 502;
    public const STATUS_CODE_503 = 503;
    public const STATUS_CODE_504 = 504;
    public const STATUS_CODE_505 = 505;
    public const STATUS_CODE_506 = 506;
    public const STATUS_CODE_507 = 507;
    public const STATUS_CODE_508 = 508;
    public const STATUS_CODE_510 = 510;
    public const STATUS_CODE_511 = 511;
    public const STATUS_CODE_599 = 599;
    /**#@-*/
    /**
     * @internal
     */
    public const MIN_STATUS_CODE_VALUE = 100;
    /**
     * @internal
     */
    public const MAX_STATUS_CODE_VALUE = 599;
    /** @var array Recommended Reason Phrases */
    protected $recommended_reason_phrases = [
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        // Deprecated
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        499 => 'Client Closed Request',
        // SERVER ERROR
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    ];
    /** @var int Status code */
    protected $status_code = 200;
    /** @var string|null Null means it will be looked up from the $reasonPhrase list above */
    protected $reason_phrase;
    /**
     * Populate object from string
     *
     * @param  string $string
     * @return static
     * @throws Exception\InvalidArgumentException
     */
    public static function from_string($string)
    {
        $lines = explode("\r\n", $string);
        if (count($lines) === 1) {
            $lines = explode("\n", $string);
        }
        $first_line = array_shift($lines);
        $response = new static();
        $response->parse_status_line($first_line);
        /**
         * @link https://tools.ietf.org/html/rfc7231#section-6.2.1
         */
        if ($response->status_code === static::STATUS_CODE_100) {
            $next = array_shift($lines);
            // take next line
            $next = empty($next) ? array_shift($lines) : $next;
            // take next or skip if empty
            $response->parse_status_line($next);
        }
        if (count($lines) === 0) {
            return $response;
        }
        $is_header = true;
        $headers = $content = [];
        foreach ($lines as $line) {
            if ($is_header && $line === '') {
                $is_header = false;
                continue;
            }
            if ($is_header) {
                if (preg_match("/[\r\n]/", $line)) {
                    throw new Exception\RuntimeException('CRLF injection detected');
                }
                $headers[] = $line;
                continue;
            }
            if (empty($content) && preg_match('/^[a-z0-9!#$%&\'*+.^_`|~-]+:$/i', $line)) {
                throw new Exception\RuntimeException('CRLF injection detected');
            }
            $content[] = $line;
        }
        if ($headers) {
            $response->headers = implode("\r\n", $headers);
        }
        if ($content) {
            $response->set_content(implode("\r\n", $content));
        }
        return $response;
    }
    /**
     * @param string $line
     * @throws Exception\InvalidArgumentException
     * @throws RuntimeException
     */
    protected function parse_status_line($line)
    {
        $regex = '/^HTTP\/(?P<version>1\.[01]|2) (?P<status>\d{3})(?:[ ]+(?P<reason>.*))?$/';
        $matches = [];
        if (!preg_match($regex, $line, $matches)) {
            throw new Exception\InvalidArgumentException('A valid response status line was not found in the provided string');
        }
        $this->version = $matches['version'];
        $this->set_status_code($matches['status']);
        $this->set_reason_phrase($matches['reason'] ?? '');
    }
    /**
     * @return Header\SetCookie[]
     */
    public function get_cookie()
    {
        return $this->get_headers()->get('Set-Cookie');
    }
    /**
     * Set HTTP status code and (optionally) message
     *
     * @param  int $code
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function set_status_code($code)
    {
        if (!is_numeric($code) || is_float($code) || $code < static::MIN_STATUS_CODE_VALUE || $code > static::MAX_STATUS_CODE_VALUE) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid status code "%s"; must be an integer between %d and %d, inclusive', is_scalar($code) ? $code : gettype($code), static::MIN_STATUS_CODE_VALUE, static::MAX_STATUS_CODE_VALUE));
        }
        return $this->save_status_code($code);
    }
    /**
     * Retrieve HTTP status code
     *
     * @return int
     */
    public function get_status_code()
    {
        return $this->status_code;
    }
    /**
     * Set custom HTTP status code
     *
     * @param  int $code
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function set_custom_status_code($code)
    {
        if (!is_numeric($code)) {
            $code = is_scalar($code) ? $code : gettype($code);
            throw new Exception\InvalidArgumentException(sprintf('Invalid status code provided: "%s"', $code));
        }
        return $this->save_status_code($code);
    }
    /**
     * Assign status code
     *
     * @param int $code
     * @return $this
     */
    protected function save_status_code($code)
    {
        $this->reason_phrase = null;
        $this->status_code = (int) $code;
        return $this;
    }
    /**
     * @param string $reasonPhrase
     * @return $this
     */
    public function set_reason_phrase($reason_phrase)
    {
        $this->reason_phrase = trim($reason_phrase);
        return $this;
    }
    /**
     * Get HTTP status message
     *
     * @return string
     */
    public function get_reason_phrase()
    {
        if (empty($this->reason_phrase) && isset($this->recommended_reason_phrases[$this->status_code])) {
            $this->reason_phrase = $this->recommended_reason_phrases[$this->status_code];
        }
        return $this->reason_phrase;
    }
    /**
     * Get the body of the response
     *
     * @return string
     */
    public function get_body()
    {
        $body = (string) $this->get_content();
        $transfer_encoding = $this->get_headers()->get('Transfer-Encoding');
        if (!empty($transfer_encoding)) {
            if (strtolower($transfer_encoding->get_field_value()) === 'chunked') {
                $body = $this->decode_chunked_body($body);
            }
        }
        $content_encoding = $this->get_headers()->get('Content-Encoding');
        if (!empty($content_encoding)) {
            $content_encoding = $content_encoding->get_field_value();
            if ($content_encoding === 'gzip') {
                $body = $this->decode_gzip($body);
            } elseif ($content_encoding === 'deflate') {
                $body = $this->decode_deflate($body);
            }
        }
        return $body;
    }
    /**
     * Does the status code indicate a client error?
     *
     * @return bool
     */
    public function is_client_error()
    {
        $code = $this->get_status_code();
        return $code < 500 && $code >= 400;
    }
    /**
     * Is the request forbidden due to ACLs?
     *
     * @return bool
     */
    public function is_forbidden()
    {
        return 403 === $this->get_status_code();
    }
    /**
     * Is the current status "informational"?
     *
     * @return bool
     */
    public function is_informational()
    {
        $code = $this->get_status_code();
        return $code >= 100 && $code < 200;
    }
    /**
     * Does the status code indicate the resource is not found?
     *
     * @return bool
     */
    public function is_not_found()
    {
        return 404 === $this->get_status_code();
    }
    /**
     * Does the status code indicate the resource is gone?
     *
     * @return bool
     */
    public function is_gone()
    {
        return 410 === $this->get_status_code();
    }
    /**
     * Do we have a normal, OK response?
     *
     * @return bool
     */
    public function is_ok()
    {
        return 200 === $this->get_status_code();
    }
    /**
     * Does the status code reflect a server error?
     *
     * @return bool
     */
    public function is_server_error()
    {
        $code = $this->get_status_code();
        return 500 <= $code && 600 > $code;
    }
    /**
     * Do we have a redirect?
     *
     * @return bool
     */
    public function is_redirect()
    {
        $code = $this->get_status_code();
        return 300 <= $code && 400 > $code;
    }
    /**
     * Was the response successful?
     *
     * @return bool
     */
    public function is_success()
    {
        $code = $this->get_status_code();
        return 200 <= $code && 300 > $code;
    }
    /**
     * Render the status line header
     *
     * @return string
     */
    public function render_status_line()
    {
        $status = sprintf('HTTP/%s %d %s', $this->get_version(), $this->get_status_code(), $this->get_reason_phrase());
        return trim($status);
    }
    /**
     * Render entire response as HTTP response string
     *
     * @return string
     */
    public function to_string()
    {
        $str = $this->render_status_line() . "\r\n";
        $str .= $this->get_headers()->to_string();
        $str .= "\r\n";
        return $str . $this->get_content();
    }
    /**
     * Decode a "chunked" transfer-encoded body and return the decoded text
     *
     * @param  string $body
     * @return string
     * @throws RuntimeException
     */
    protected function decode_chunked_body($body)
    {
        $dec_body = '';
        $offset = 0;
        while (true) {
            if (!preg_match("/^([\\da-fA-F]+)[^\r\n]*\r\n/sm", $body, $m, 0, $offset)) {
                if (trim(substr($body, $offset))) {
                    // Message was not consumed completely!
                    throw new Exception\RuntimeException('Error parsing body - doesn\'t seem to be a chunked message');
                }
                // Message was consumed completely
                break;
            }
            $length = hexdec(trim($m[1]));
            $cut = strlen($m[0]);
            $dec_body .= substr($body, $offset + $cut, $length);
            $offset += $cut + $length + 2;
        }
        return $dec_body;
    }
    /**
     * Decode a gzip encoded message (when Content-encoding = gzip)
     *
     * Currently requires PHP with zlib support
     *
     * @param  string $body
     * @return string
     * @throws RuntimeException
     */
    protected function decode_gzip($body)
    {
        if (!function_exists('gzdecode')) {
            throw new Exception\RuntimeException('zlib extension is required in order to decode "gzip" encoding');
        }
        if ($body === '' || $this->get_headers()->has('content-length') && (int) $this->get_headers()->get('content-length')->get_field_value() === 0) {
            return '';
        }
        Error_Handler::start();
        $return = gzdecode($body);
        $test = Error_Handler::stop();
        if ($test) {
            throw new Exception\RuntimeException('Error occurred during gzip inflation', 0, $test);
        }
        return $return;
    }
    /**
     * Decode a zlib deflated message (when Content-encoding = deflate)
     *
     * Currently requires PHP with zlib support
     *
     * @param  string $body
     * @return string
     * @throws RuntimeException
     */
    protected function decode_deflate($body)
    {
        if (!function_exists('gzuncompress')) {
            throw new Exception\RuntimeException('zlib extension is required in order to decode "deflate" encoding');
        }
        if ($this->get_headers()->has('content-length') && 0 === (int) $this->get_headers()->get('content-length')->get_field_value()) {
            return '';
        }
        /**
         * Some servers (IIS ?) send a broken deflate response, without the
         * RFC-required zlib header.
         *
         * We try to detect the zlib header, and if it does not exist we
         * teat the body is plain DEFLATE content.
         *
         * This method was adapted from PEAR HTTP_Request2 by (c) Alexey Borzov
         *
         * @link https://getlaminas.org/issues/browse/Laminas-6040
         */
        $zlib_header = unpack('n', substr($body, 0, 2));
        if ($zlib_header[1] % 31 === 0 && ord($body[0]) === 0x78 && in_array(ord($body[1]), [0x1, 0x5e, 0x9c, 0xda])) {
            return gzuncompress($body);
        }
        return gzinflate($body);
    }
}