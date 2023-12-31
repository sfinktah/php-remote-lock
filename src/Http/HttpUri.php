<?php

namespace Sfinktah\RemoteLock\Http;

use Psr\Http\Message\UriInterface;

class HttpUri implements UriInterface
{
    private $scheme;
    private $user;
    private $pass;
    private $host;
    private $port;
    private $path;
    private $query;
    private $fragment;

    /**
     * HttpUri constructor.
     *
     * @param string $uri
     */
    public function __construct(string $uri) {
        $parts = parse_url($uri);

        $this->scheme = $parts['scheme'] ?? '';
        $this->user = $parts['user'] ?? '';
        $this->pass = $parts['pass'] ?? '';
        $this->host = $parts['host'] ?? '';
        $this->port = $parts['port'] ?? null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme() {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthority() {
        $authority = $this->host;

        $user = !empty($this->user) ? $this->user : '';
        $pass = !empty($this->pass) ? ':' . $this->pass : '';
        $pass = ($user || $pass) ? "$pass@" : '';

        if ($pass) {
            $authority = $pass . '@' . $authority;
        }

        if (!empty($this->port)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo(): string {
        $user = !empty($this->user) ? $this->user : '';
        $pass = !empty($this->pass) ? ':' . $this->pass : '';
        return ($user || $pass) ? "$pass@" : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery() {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment() {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme(string $scheme) {
        $new = clone $this;
        $new->scheme = $scheme;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withUserInfo(string $user, ?string $password = null) {
        $new = clone $this;
        $new->user = $user;
        $new->pass = $password;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost(string $host) {
        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort(?int $port) {
        $new = clone $this;
        $new->port = $port;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath(string $path) {
        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery(string $query) {
        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment(string $fragment) {
        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString() {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        $uri .= $this->path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    public function toString(): string {
        $scheme = !empty($this->scheme) ? $this->scheme . '://' : '';
        $host = !empty($this->host) ? $this->host : '';
        $port = !empty($this->port) ? ':' . $this->port : '';
        $user = !empty($this->user) ? $this->user : '';
        $pass = !empty($this->pass) ? ':' . $this->pass : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = !empty($this->path) ? $this->path : '';
        $query = !empty($this->query) ? '?' . $this->query : '';
        $fragment = !empty($this->fragment) ? '#' . $this->fragment : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    public static function make(...$arguments): HttpUri {
        return new static(...$arguments);
    }
}