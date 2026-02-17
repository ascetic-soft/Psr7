<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class ServerRequest extends Request implements ServerRequestInterface
{
    /** @var array<string, mixed> */
    private array $serverParams;

    /** @var array<mixed> */
    private array $cookieParams = [];

    /** @var array<string, mixed> */
    private array $queryParams = [];

    /** @var array<mixed> */
    private array $uploadedFiles = [];

    /** @var null|array<mixed>|object */
    private null|array|object $parsedBody = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, mixed>               $serverParams
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        ?StreamInterface $body = null,
        string $protocolVersion = '1.1',
        array $serverParams = [],
    ) {
        parent::__construct($method, $uri, $headers, $body, $protocolVersion);
        $this->serverParams = $serverParams;
    }

    /** @return array<string, mixed> */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /** @return array<mixed> */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /** @param array<mixed> $cookies */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;

        return $new;
    }

    /** @return array<string, mixed> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /** @param array<mixed> $query */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    /** @return array<mixed> */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /** @param array<mixed> $uploadedFiles */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    /** @return null|array<mixed>|object */
    public function getParsedBody(): null|array|object
    {
        return $this->parsedBody;
    }

    /** @param null|array<mixed>|object $data */
    public function withParsedBody(mixed $data): ServerRequestInterface
    {
        if ($data !== null && !\is_array($data) && !\is_object($data)) { // @phpstan-ignore booleanAnd.alwaysFalse, function.alreadyNarrowedType
            throw new \InvalidArgumentException('Parsed body must be an array, object, or null');
        }

        $new = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        if (\array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        return $default;
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (!\array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);

        return $new;
    }
}
