<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7;

use Psr\Http\Message\StreamInterface;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    private ?int $size;

    private bool $seekable;

    private bool $readable;

    private bool $writable;

    /** @var array<string, bool> */
    private const READABLE_MODES = [
        'r' => true, 'r+' => true, 'w+' => true, 'a+' => true, 'x+' => true, 'c+' => true,
        'rb' => true, 'r+b' => true, 'w+b' => true, 'a+b' => true, 'x+b' => true, 'c+b' => true,
        'rt' => true, 'r+t' => true, 'w+t' => true, 'a+t' => true, 'x+t' => true, 'c+t' => true,
    ];

    /** @var array<string, bool> */
    private const WRITABLE_MODES = [
        'w' => true, 'w+' => true, 'r+' => true, 'a' => true, 'a+' => true, 'x' => true, 'x+' => true, 'c' => true, 'c+' => true,
        'wb' => true, 'w+b' => true, 'r+b' => true, 'ab' => true, 'a+b' => true, 'xb' => true, 'x+b' => true, 'cb' => true, 'c+b' => true,
        'wt' => true, 'w+t' => true, 'r+t' => true, 'at' => true, 'a+t' => true, 'xt' => true, 'x+t' => true, 'ct' => true, 'c+t' => true,
    ];

    /**
     * @param resource $resource
     */
    public function __construct($resource, ?int $size = null)
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        $this->resource = $resource;
        $this->size = $size;

        $meta = stream_get_meta_data($resource);
        $this->seekable = $meta['seekable'];
        $this->readable = isset(self::READABLE_MODES[$meta['mode']]);
        $this->writable = isset(self::WRITABLE_MODES[$meta['mode']]);
    }

    /**
     * Create a stream from a string.
     */
    public static function create(string $content = ''): self
    {
        $resource = fopen('php://temp', 'r+');

        if ($resource === false) {
            throw new \RuntimeException('Unable to create stream');
        }

        if ($content !== '') {
            fwrite($resource, $content);
            fseek($resource, 0);
        }

        return new self($resource, \strlen($content));
    }

    public function __toString(): string
    {
        try {
            if ($this->resource === null) {
                return '';
            }

            if ($this->seekable) {
                fseek($this->resource, 0);
            }

            return stream_get_contents($this->resource) ?: '';
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            $resource = $this->detach();
            if ($resource !== null) {
                fclose($resource);
            }
        }
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->size = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        if ($this->size !== null) {
            return $this->size;
        }

        $stats = fstat($this->resource);

        return $this->size = ($stats !== false ? $stats['size'] : null);
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        $position = ftell($this->resource);

        if ($position === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->seekable) {
            throw new \RuntimeException('Stream is not seekable');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position');
        }

        $this->size = null;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->writable) {
            throw new \RuntimeException('Stream is not writable');
        }

        $result = fwrite($this->resource, $string);

        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream');
        }

        $this->size = null;

        return $result;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->readable) {
            throw new \RuntimeException('Stream is not readable');
        }

        if ($length < 1) {
            throw new \RuntimeException('Length must be greater than 0');
        }

        $result = fread($this->resource, $length);

        if ($result === false) {
            throw new \RuntimeException('Unable to read from stream');
        }

        return $result;
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->readable) {
            throw new \RuntimeException('Stream is not readable');
        }

        $contents = stream_get_contents($this->resource);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        if ($this->resource === null) {
            return $key !== null ? null : [];
        }

        $meta = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}
