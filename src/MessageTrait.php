<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Shared logic for MessageInterface implementations.
 *
 * Uses a dual-array strategy for O(1) case-insensitive header lookups:
 *  - $headers:     original-case name => list of values
 *  - $headerNames: lowercase name     => original-case name
 */
trait MessageTrait
{
    /** @var array<string, list<string>> Header values keyed by original-case name. */
    private array $headers = [];

    /** @var array<string, string> Lowercase header name => original-case header name. */
    private array $headerNames = [];

    private string $protocol = '1.1';

    private StreamInterface $stream;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion(string $version): static
    {
        if ($version === $this->protocol) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = $version;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $lower = strtolower($name);

        if (!isset($this->headerNames[$lower])) {
            return [];
        }

        return $this->headers[$this->headerNames[$lower]];
    }

    public function getHeaderLine(string $name): string
    {
        $values = $this->getHeader($name);

        return $values === [] ? '' : implode(', ', $values);
    }

    public function withHeader(string $name, $value): static
    {
        $values = self::normalizeHeaderValue($value);
        $lower = strtolower($name);

        $new = clone $this;

        if (isset($new->headerNames[$lower])) {
            unset($new->headers[$new->headerNames[$lower]]);
        }

        $new->headerNames[$lower] = $name;
        $new->headers[$name] = $values;

        return $new;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $values = self::normalizeHeaderValue($value);
        $lower = strtolower($name);

        $new = clone $this;

        if (isset($new->headerNames[$lower])) {
            $originalName = $new->headerNames[$lower];
            $new->headers[$originalName] = array_merge($new->headers[$originalName], $values);
        } else {
            $new->headerNames[$lower] = $name;
            $new->headers[$name] = $values;
        }

        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $lower = strtolower($name);

        if (!isset($this->headerNames[$lower])) {
            return $this;
        }

        $new = clone $this;
        $originalName = $new->headerNames[$lower];
        unset($new->headers[$originalName], $new->headerNames[$lower]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        if (!isset($this->stream)) {
            $this->stream = Stream::create();
        }

        return $this->stream;
    }

    public function withBody(StreamInterface $body): static
    {
        if ($body === ($this->stream ?? null)) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $name = (string) $name;
            $values = self::normalizeHeaderValue($value);
            $lower = strtolower($name);

            if (isset($this->headerNames[$lower])) {
                $originalName = $this->headerNames[$lower];
                $this->headers[$originalName] = array_merge($this->headers[$originalName], $values);
            } else {
                $this->headerNames[$lower] = $name;
                $this->headers[$name] = $values;
            }
        }
    }

    /**
     * @param string|string[] $value
     * @return list<string>
     */
    private static function normalizeHeaderValue(string|array $value): array
    {
        if (\is_string($value)) {
            return [trim($value, " \t")];
        }

        if ($value === []) {
            throw new \InvalidArgumentException('Header value must be a string or non-empty array of strings');
        }

        $normalized = [];
        foreach ($value as $v) {
            $normalized[] = trim((string) $v, " \t");
        }

        return $normalized;
    }
}
