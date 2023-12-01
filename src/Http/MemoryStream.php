<?php

namespace Sfinktah\Shopify\Http;

use Psr\Http\Message\StreamInterface;

class MemoryStream implements StreamInterface
{
    private $stream;

    /**
     * MemoryStream constructor.
     *
     * @param string $content Initial content of the stream.
     */
    public function __construct(string $content = '')
    {
        $this->stream = fopen('php://temp', 'r+');
        fwrite($this->stream, $content);
        rewind($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getContents();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        $resource = $this->stream;
        $this->stream = null;
        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        if (is_resource($this->stream)) {
            $stats = fstat($this->stream);
            return $stats['size'] ?? null;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return is_resource($this->stream) ? ftell($this->stream) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool {
        return !is_resource($this->stream) || feof($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool {
        return is_resource($this->stream) && stream_get_meta_data($this->stream)['seekable'];
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET): bool {
        return is_resource($this->stream) && fseek($this->stream, $offset, $whence) === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): bool {
        return is_resource($this->stream) && rewind($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool {
        return is_resource($this->stream) && stream_get_meta_data($this->stream)['writable'];
    }

    /**
     * {@inheritdoc}
     */
    public function write($string)
    {
        return is_resource($this->stream) ? fwrite($this->stream, $string) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool {
        return is_resource($this->stream) && stream_get_meta_data($this->stream)['readable'];
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        return is_resource($this->stream) ? fread($this->stream, $length) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getContents()
    {
        return is_resource($this->stream) ? stream_get_contents($this->stream) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        if (!is_resource($this->stream)) {
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->stream);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    public static function make(...$arguments): static {
        return new static(...$arguments);
    }
}