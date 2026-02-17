<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface
{
    use MessageTrait;

    private string $method;

    private string $requestTarget = '';

    private UriInterface $uri;

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        ?StreamInterface $body = null,
        string $protocolVersion = '1.1',
    ) {
        $this->method = $method;
        $this->uri = \is_string($uri) ? new Uri($uri) : $uri;
        $this->protocol = $protocolVersion;

        $this->setHeaders($headers);

        if ($body !== null) {
            $this->stream = $body;
        }

        if (!$this->hasHeader('host') && $this->uri->getHost() !== '') {
            $this->updateHostFromUri();
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();

        if ($target === '') {
            $target = '/';
        }

        $query = $this->uri->getQuery();

        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        if ($requestTarget === $this->requestTarget) {
            return $this;
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        if ($method === $this->method) {
            return $this;
        }

        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('host')) {
            $new->updateHostFromUri();
        }

        return $new;
    }

    private function updateHostFromUri(): void
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        $port = $this->uri->getPort();

        if ($port !== null) {
            $host .= ':' . $port;
        }

        // Ensure Host is the first header (per PSR-7 recommendation).
        $lower = 'host';

        if (isset($this->headerNames[$lower])) {
            $originalName = $this->headerNames[$lower];
            unset($this->headers[$originalName]);
        }

        $this->headerNames[$lower] = 'Host';
        $this->headers = ['Host' => [$host]] + $this->headers;
    }
}
