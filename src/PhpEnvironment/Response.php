<?php

declare (strict_types=1);
namespace Laminas\Http\Php_Environment;

use function call_user_func;
use function header;
use Laminas\Http\Header\Header_Interface;
use Laminas\Http\Header\Multiple_Header_Interface;
use Laminas\Http\Response as HttpResponse;
/**
 * HTTP Response for current PHP environment
 */
class Response extends Http_Response
{
    /**
     * @deprecated This property is deprecated, and will be removed
     *
     * @var bool
     */
    public $headers_sent;
    /**
     * The current used version
     * (The value will be detected on getVersion)
     *
     * @var null|string
     */
    protected $version;
    /** @var bool */
    protected $content_sent = false;
    /** @var null|callable */
    private $headers_sent_handler;
    /**
     * Return the HTTP version for this response
     *
     * @see \Laminas\Http\AbstractMessage::getVersion()
     *
     * @return string
     */
    public function get_version()
    {
        if (!$this->version) {
            $this->version = $this->detect_version();
        }
        return $this->version;
    }
    /**
     * Detect the current used protocol version.
     * If detection failed it falls back to version 1.0.
     *
     * @return string
     */
    protected function detect_version()
    {
        if (isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1') {
            return self::VERSION_11;
        }
        return self::VERSION_10;
    }
    /**
     * @return bool
     */
    public function headers_sent()
    {
        return headers_sent();
    }
    /**
     * @return bool
     */
    public function content_sent()
    {
        return $this->content_sent;
    }
    public function set_headers_sent_handler(callable $handler): void
    {
        $this->headers_sent_handler = $handler;
    }
    /**
     * Send HTTP headers
     *
     * @return $this
     */
    public function send_headers()
    {
        if ($this->headers_sent()) {
            if ($this->headers_sent_handler) {
                call_user_func($this->headers_sent_handler, $this);
            }
            return $this;
        }
        $status = $this->render_status_line();
        header($status);
        /** @var HeaderInterface $header */
        foreach ($this->get_headers() as $header) {
            if ($header instanceof Multiple_Header_Interface) {
                header($header->to_string(), false);
                continue;
            }
            header($header->to_string());
        }
        $this->headers_sent = true;
        return $this;
    }
    /**
     * Send content
     *
     * @return $this
     */
    public function send_content()
    {
        if ($this->content_sent()) {
            return $this;
        }
        echo $this->get_content();
        $this->content_sent = true;
        return $this;
    }
    /**
     * Send HTTP response
     *
     * @return $this
     */
    public function send()
    {
        $this->send_headers()->send_content();
        return $this;
    }
}