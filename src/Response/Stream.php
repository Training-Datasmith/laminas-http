<?php

declare (strict_types=1);
namespace Laminas\Http\Response;

use function array_shift;
use const E_WARNING;
use function explode;
use function fgets;
use function file_exists;
use function get_resource_type;
use function implode;
use function is_resource;
use function is_string;
use Laminas\Http\Exception;
use Laminas\Http\Header\Content_Length;
use Laminas\Http\Response;
use Laminas\Stdlib\Error_Handler;
use function sprintf;
use function stream_get_contents;
use function strlen;
use function trim;
use function unlink;
/**
 * Represents an HTTP response message as PHP stream resource
 */
class Stream extends Response
{
    /**
     * The Content-Length value, if set
     *
     * @var int
     */
    protected $content_length;
    /**
     * The portion of the body that has already been streamed
     *
     * @var int
     */
    protected $content_streamed = 0;
    /**
     * Response as stream
     *
     * @var resource
     */
    protected $stream;
    /**
     * The name of the file containing the stream
     *
     * Will be empty if stream is not file-based.
     *
     * @var string
     */
    protected $stream_name;
    /**
     * Should we clean up the stream file when this response is closed?
     *
     * @var bool
     */
    protected $cleanup;
    /**
     * Set content length
     *
     * @param int $contentLength
     */
    public function set_content_length($content_length = null): void
    {
        $this->content_length = $content_length;
    }
    /**
     * Get content length
     *
     * @return int|null
     */
    public function get_content_length()
    {
        return $this->content_length;
    }
    /**
     * Get the response as stream
     *
     * @return resource
     */
    public function get_stream()
    {
        return $this->stream;
    }
    /**
     * Set the response stream
     *
     * @param resource $stream
     * @return $this
     */
    public function set_stream($stream)
    {
        $this->stream = $stream;
        return $this;
    }
    /**
     * Get the cleanup trigger
     *
     * @return bool
     */
    public function get_cleanup()
    {
        return $this->cleanup;
    }
    /**
     * Set the cleanup trigger
     *
     * @param bool $cleanup
     */
    public function set_cleanup($cleanup = true): void
    {
        $this->cleanup = $cleanup;
    }
    /**
     * Get file name associated with the stream
     *
     * @return string
     */
    public function get_stream_name()
    {
        return $this->stream_name;
    }
    /**
     * Set file name associated with the stream
     *
     * @param string $streamName Name to set
     * @return $this
     */
    public function set_stream_name($stream_name)
    {
        $this->stream_name = $stream_name;
        return $this;
    }
    /**
     * Create a new Laminas\Http\Response\Stream object from a stream
     *
     * @param  string $responseString
     * @param  resource $stream
     * @return $this
     * @throws Exception\InvalidArgumentException
     * @throws Exception\OutOfRangeException
     */
    public static function from_stream($response_string, $stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new Exception\InvalidArgumentException('A valid stream is required');
        }
        $header_complete = false;
        $headers_string = '';
        $response_array = [];
        if ($response_string) {
            $response_array = explode("\n", $response_string);
        }
        while (!empty($response_array)) {
            $next_line = array_shift($response_array);
            $headers_string .= $next_line . "\n";
            $next_line_trimmed = trim($next_line);
            if ($next_line_trimmed === '') {
                $header_complete = true;
                break;
            }
        }
        if (!$header_complete) {
            while (false !== $next_line = fgets($stream)) {
                $headers_string .= trim($next_line) . "\r\n";
                if ($next_line === "\r\n" || $next_line === "\n") {
                    $header_complete = true;
                    break;
                }
            }
        }
        if (!$header_complete) {
            throw new Exception\OutOfRangeException('End of header not found');
        }
        /** @var Stream $response */
        $response = static::from_string($headers_string);
        if (is_resource($stream)) {
            $response->set_stream($stream);
        }
        if (!empty($response_array)) {
            $response->content = implode("\n", $response_array);
        }
        $headers = $response->get_headers();
        foreach ($headers as $header) {
            if ($header instanceof Content_Length) {
                $response->set_content_length((int) $header->get_field_value());
                $content_length = $response->get_content_length();
                if (strlen((string) $response->content) > $content_length) {
                    throw new Exception\OutOfRangeException(sprintf('Too much content was extracted from the stream (%d instead of %d bytes)', strlen((string) $response->content), $content_length));
                }
                break;
            }
        }
        return $response;
    }
    /**
     * Get the response body as string
     *
     * This method returns the body of the HTTP response (the content), as it
     * should be in it's readable version - that is, after decoding it (if it
     * was decoded), deflating it (if it was gzip compressed), etc.
     *
     * If you want to get the raw body (as transferred on wire) use
     * $this->getRawBody() instead.
     *
     * @return string
     */
    public function get_body()
    {
        if ($this->stream !== null) {
            $this->read_stream();
        }
        return parent::get_body();
    }
    /**
     * Get the raw response body (as transferred "on wire") as string
     *
     * If the body is encoded (with Transfer-Encoding, not content-encoding -
     * IE "chunked" body), gzip compressed, etc. it will not be decoded.
     *
     * @return string
     */
    public function get_raw_body()
    {
        if ($this->stream) {
            $this->read_stream();
        }
        return $this->content;
    }
    /**
     * Read stream content and return it as string
     *
     * Function reads the remainder of the body from the stream and closes the stream.
     *
     * @return string
     */
    protected function read_stream()
    {
        $content_length = $this->get_content_length();
        if (null !== $content_length) {
            $bytes = $content_length - $this->content_streamed;
        } else {
            $bytes = -1;
            // Read the whole buffer
        }
        if (!is_resource($this->stream) || $bytes === 0) {
            return '';
        }
        $this->content .= stream_get_contents($this->stream, $bytes);
        $this->content_streamed += strlen($this->content);
        if ($this->get_content_length() === $this->content_streamed) {
            $this->stream = null;
        }
    }
    /**
     * Destructor
     */
    public function __destruct()
    {
        if (is_resource($this->stream)) {
            $this->stream = null;
            //Could be listened by others
        }
        if ($this->cleanup && is_string($this->stream_name) && file_exists($this->stream_name)) {
            Error_Handler::start(E_WARNING);
            unlink($this->stream_name);
            Error_Handler::stop();
        }
    }
}