<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace Sfinktah\Shopify\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class HttpRequest implements RequestInterface
{
    private $method;
    private $uri;
    private $headers;
    private $body;
    private $protocolVersion; // Default protocol version

    /**
     * HttpRequest constructor.
     *
     * @param string $method HTTP method (GET, POST, etc.).
     * @param UriInterface|string $uri URI object or string.
     * @param array $headers HTTP headers.
     * @param \Psr\Http\Message\StreamInterface|null $body HTTP request body.
     * @param string $protocolVersion HTTP protocol version.
     */
    public function __construct(string $method = 'GET', $uri = '', array $headers = [], StreamInterface $body = null, string $protocolVersion = '1.1') {
        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new HttpUri($uri);
        $this->headers = $headers;
        $this->body = $body instanceof StreamInterface ? $body : new MemoryStream('');
        $this->protocolVersion = $protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget() {
        $target = $this->uri->getPath();
        if ($query = $this->uri->getQuery()) {
            $target .= '?' . $query;
        }

        return $target ?: '/';
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget($requestTarget) {
        if (strpos($requestTarget, ' ') !== false) {
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }

        $new = clone $this;
        $new->uri = $new->uri->withPath($requestTarget);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod($method) {
        $new = clone $this;
        $new->method = strtoupper($method);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri() {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function withUri(UriInterface $uri, $preserveHost = false) {
        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost) {
            $new = $new->withoutHeader('Host');

            // If the new URI has a host, update the Host header
            if ($uri->getHost()) {
                $new = $new->withHeader('Host', $uri->getHost());
            }
        }

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader($name): bool {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name) {
        $name = strtolower($name);
        return $this->headers[$name] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine($name): string {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader($name, $value) {
        $normalized = strtolower($name);
        $new = clone $this;
        $new->headers[$normalized] = is_array($value) ? $value : [$value];

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader($name, $value) {
        $normalized = strtolower($name);
        $new = clone $this;
        $new->headers[$normalized] = array_merge($this->getHeader($normalized), is_array($value) ? $value : [$value]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader($name) {
        $normalized = strtolower($name);
        $new = clone $this;
        unset($new->headers[$normalized]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): ?StreamInterface {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body) {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string {
        return $this->protocolVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion($version) {
        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    public static function make(...$arguments): static {
        return new static(...$arguments);
    }

}