<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Creates a ServerRequest from PHP superglobals.
 */
final class ServerRequestCreator
{
    /**
     * Create a ServerRequest from PHP superglobals.
     *
     * @param array<string, mixed>|null  $server  Defaults to $_SERVER.
     * @param array<string, mixed>|null  $get     Defaults to $_GET.
     * @param array<string, mixed>|null  $post    Defaults to $_POST.
     * @param array<string, string>|null $cookies Defaults to $_COOKIE.
     * @param array<string, mixed>|null  $files   Defaults to $_FILES.
     */
    public static function fromGlobals(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
    ): ServerRequestInterface {
        $server ??= $_SERVER;
        $get ??= $_GET;
        $post ??= $_POST;
        $cookies ??= $_COOKIE;
        $files ??= $_FILES;

        $method = isset($server['REQUEST_METHOD']) && \is_string($server['REQUEST_METHOD'])
            ? $server['REQUEST_METHOD']
            : 'GET';

        $uri = self::createUriFromServer($server);
        $headers = self::extractHeaders($server);
        $protocol = self::extractProtocol($server);

        $resource = fopen('php://input', 'r');
        if ($resource === false) {
            throw new \RuntimeException('Unable to open php://input');
        }
        $body = new Stream($resource);

        $request = new ServerRequest($method, $uri, $headers, $body, $protocol, $server);
        $request = $request
            ->withCookieParams($cookies)
            ->withQueryParams($get)
            ->withUploadedFiles(self::normalizeFiles($files));

        if ($method === 'POST') {
            $contentType = $request->getHeaderLine('content-type');

            if (str_contains($contentType, 'application/x-www-form-urlencoded')
                || str_contains($contentType, 'multipart/form-data')) {
                $request = $request->withParsedBody($post);
            }
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function createUriFromServer(array $server): Uri
    {
        $uri = new Uri();

        // Scheme.
        $https = $server['HTTPS'] ?? '';
        $scheme = (\is_string($https) && $https !== '' && $https !== 'off') ? 'https' : 'http';
        $uri = $uri->withScheme($scheme);

        // Host and port.
        if (isset($server['HTTP_HOST']) && \is_string($server['HTTP_HOST'])) {
            $host = $server['HTTP_HOST'];
            if (str_contains($host, ':')) {
                [$host, $port] = explode(':', $host, 2);
                $uri = $uri->withPort((int) $port);
            }
            $uri = $uri->withHost($host);
        } elseif (isset($server['SERVER_NAME']) && \is_string($server['SERVER_NAME'])) {
            $uri = $uri->withHost($server['SERVER_NAME']);
            if (isset($server['SERVER_PORT']) && is_numeric($server['SERVER_PORT'])) {
                $uri = $uri->withPort((int) $server['SERVER_PORT']);
            }
        }

        // Path.
        $requestUri = isset($server['REQUEST_URI']) && \is_string($server['REQUEST_URI'])
            ? $server['REQUEST_URI']
            : '/';

        $path = parse_url($requestUri, PHP_URL_PATH);
        $uri = $uri->withPath(\is_string($path) ? $path : '/');

        // Query.
        if (isset($server['QUERY_STRING']) && \is_string($server['QUERY_STRING'])) {
            $uri = $uri->withQuery($server['QUERY_STRING']);
        } else {
            $query = parse_url($requestUri, PHP_URL_QUERY);
            if (\is_string($query)) {
                $uri = $uri->withQuery($query);
            }
        }

        return $uri;
    }

    /**
     * Extract HTTP headers from $_SERVER.
     *
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!\is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function extractProtocol(array $server): string
    {
        if (isset($server['SERVER_PROTOCOL']) && \is_string($server['SERVER_PROTOCOL'])) {
            return str_replace('HTTP/', '', $server['SERVER_PROTOCOL']);
        }

        return '1.1';
    }

    /**
     * Normalize $_FILES into a tree of UploadedFileInterface instances.
     *
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (\is_array($value)) {
                if (isset($value['tmp_name'])) {
                    $normalized[$key] = self::createUploadedFileFromSpec($value);
                } else {
                    /** @var array<string, mixed> $value */
                    $normalized[$key] = self::normalizeFiles($value);
                }
            }
        }

        return $normalized;
    }

    /**
     * Create UploadedFile instance(s) from a single $_FILES entry.
     *
     * @param array<string, mixed> $value
     * @return UploadedFileInterface|array<int|string, mixed>
     */
    private static function createUploadedFileFromSpec(array $value): UploadedFileInterface|array
    {
        if (\is_array($value['tmp_name'])) {
            $files = [];
            /** @var int|string $k */
            foreach ($value['tmp_name'] as $k => $tmpName) {
                /** @var array<string, mixed> $sizeArr */
                $sizeArr = \is_array($value['size'] ?? null) ? $value['size'] : [];
                /** @var array<string, mixed> $errorArr */
                $errorArr = \is_array($value['error'] ?? null) ? $value['error'] : [];
                /** @var array<string, mixed> $nameArr */
                $nameArr = \is_array($value['name'] ?? null) ? $value['name'] : [];
                /** @var array<string, mixed> $typeArr */
                $typeArr = \is_array($value['type'] ?? null) ? $value['type'] : [];

                $files[$k] = self::createUploadedFileFromSpec([
                    'tmp_name' => $tmpName,
                    'size' => $sizeArr[$k] ?? null,
                    'error' => $errorArr[$k] ?? \UPLOAD_ERR_NO_FILE,
                    'name' => $nameArr[$k] ?? null,
                    'type' => $typeArr[$k] ?? null,
                ]);
            }

            return $files;
        }

        $tmpName = isset($value['tmp_name']) && \is_string($value['tmp_name']) ? $value['tmp_name'] : '';
        $size = isset($value['size']) && is_numeric($value['size']) ? (int) $value['size'] : null;
        $error = isset($value['error']) && is_numeric($value['error']) ? (int) $value['error'] : \UPLOAD_ERR_OK;
        $clientFilename = isset($value['name']) && \is_string($value['name']) ? $value['name'] : null;
        $clientMediaType = isset($value['type']) && \is_string($value['type']) ? $value['type'] : null;

        return new UploadedFile($tmpName, $size, $error, $clientFilename, $clientMediaType);
    }
}
