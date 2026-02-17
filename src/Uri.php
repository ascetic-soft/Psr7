<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7;

use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    private string $scheme = '';

    private string $userInfo = '';

    private string $host = '';

    private ?int $port = null;

    private string $path = '';

    private string $query = '';

    private string $fragment = '';

    /** Cached string representation — invalidated on any with*() call. */
    private ?string $composedUri = null;

    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);

            if ($parts === false) {
                throw new \InvalidArgumentException(\sprintf('Unable to parse URI: "%s"', $uri));
            }

            $this->applyParts($parts);
        }
    }

    public function __toString(): string
    {
        return $this->composedUri ?? ($this->composedUri = self::composeUri(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment,
        ));
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): UriInterface
    {
        $scheme = strtolower($scheme);

        if ($scheme === $this->scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->composedUri = null;
        $new->port = $new->filterPort($new->port);

        return $new;
    }

    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $info = $user;
        if ($password !== null && $password !== '') {
            $info .= ':' . $password;
        }

        if ($info === $this->userInfo) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;
        $new->composedUri = null;

        return $new;
    }

    public function withHost(string $host): UriInterface
    {
        $host = strtolower($host);

        if ($host === $this->host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;
        $new->composedUri = null;

        return $new;
    }

    public function withPort(?int $port): UriInterface
    {
        if ($port !== null && ($port < 0 || $port > 65535)) {
            throw new \InvalidArgumentException(\sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
        }

        $port = $this->filterPort($port);

        if ($port === $this->port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;
        $new->composedUri = null;

        return $new;
    }

    public function withPath(string $path): UriInterface
    {
        $path = self::encodePath($path);

        if ($path === $this->path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;
        $new->composedUri = null;

        return $new;
    }

    public function withQuery(string $query): UriInterface
    {
        $query = self::encodeQueryOrFragment($query);

        if ($query === $this->query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;
        $new->composedUri = null;

        return $new;
    }

    public function withFragment(string $fragment): UriInterface
    {
        $fragment = self::encodeQueryOrFragment($fragment);

        if ($fragment === $this->fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;
        $new->composedUri = null;

        return $new;
    }

    /**
     * @param array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string} $parts
     */
    private function applyParts(array $parts): void
    {
        $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
        $this->path = isset($parts['path']) ? self::encodePath($parts['path']) : '';
        $this->query = isset($parts['query']) ? self::encodeQueryOrFragment($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? self::encodeQueryOrFragment($parts['fragment']) : '';

        $this->userInfo = $parts['user'] ?? '';
        if (isset($parts['pass']) && $parts['pass'] !== '') {
            $this->userInfo .= ':' . $parts['pass'];
        }
    }

    private function filterPort(?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if (isset(self::DEFAULT_PORTS[$this->scheme]) && self::DEFAULT_PORTS[$this->scheme] === $port) {
            return null;
        }

        return $port;
    }

    private static function composeUri(string $scheme, string $authority, string $path, string $query, string $fragment): string
    {
        $uri = '';

        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }

        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        if ($path !== '') {
            if ($authority !== '' && !str_starts_with($path, '/')) {
                $path = '/' . $path;
            } elseif ($authority === '' && str_starts_with($path, '//')) {
                $path = '/' . ltrim($path, '/');
            }
        }

        $uri .= $path;

        if ($query !== '') {
            $uri .= '?' . $query;
        }

        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    private static function encodePath(string $path): string
    {
        return preg_replace_callback(
            '/[^a-zA-Z0-9_\-.~!$&\'()*+,;=:@\/%]++|%(?![A-Fa-f0-9]{2})/',
            static fn (array $match): string => rawurlencode($match[0]),
            $path,
        ) ?? $path;
    }

    private static function encodeQueryOrFragment(string $str): string
    {
        return preg_replace_callback(
            '/[^a-zA-Z0-9_\-.~!$&\'()*+,;=:@\/?%]++|%(?![A-Fa-f0-9]{2})/',
            static fn (array $match): string => rawurlencode($match[0]),
            $str,
        ) ?? $str;
    }
}
