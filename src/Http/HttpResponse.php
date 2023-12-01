<?php

namespace Sfinktah\Shopify\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class HttpResponse implements ResponseInterface
{
    private $statusCode;
    private $reasonPhrase;
    private $headers;
    private $body;
    private $protocolVersion = '1.1'; // Default protocol version

    /**
     * HttpResponse constructor.
     *
     * @param int               $statusCode   HTTP status code.
     * @param array             $headers      HTTP headers.
     * @param StreamInterface   $body         HTTP response body.
     * @param string            $reasonPhrase Reason phrase (optional).
     */
    public function __construct($statusCode, array $headers, StreamInterface $body, $reasonPhrase = '')
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->reasonPhrase = $reasonPhrase;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name)
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value)
    {
        $normalized = strtolower($name);
        $new = clone $this;
        $new->headers[$normalized] = is_array($value) ? $value : [$value];

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value)
    {
        $normalized = strtolower($name);
        $new = clone $this;
        $new->headers[$normalized] = array_merge($this->getHeader($normalized), is_array($value) ? $value : [$value]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name)
    {
        $normalized = strtolower($name);
        $new = clone $this;
        unset($new->headers[$normalized]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body)
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader(string $name): bool {
        $normalized = strtolower($name);
        return isset($this->headers[$normalized]);
    }


    // ... (constructor and other methods)

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string {
        return $this->protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine($name): string {
        $normalized = strtolower($name);
        return implode(', ', $this->getHeader($normalized));
    }

    public function setProtocolVersion($protocol) {
        $this->protocolVersion = $protocol;
    }
}
