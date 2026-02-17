# AsceticSoft PSR-7

A lightweight, performance-optimized [PSR-7](https://www.php-fig.org/psr/psr-7/) (HTTP Messages) and [PSR-17](https://www.php-fig.org/psr/psr-17/) (HTTP Factories) implementation for PHP 8.4+.

Designed as a companion library for [Waypoint](https://github.com/ascetic-soft/Waypoint) router, but works with any PSR-7/PSR-15/PSR-17 compatible stack.

## Features

- **Full PSR-7 compliance** — implements all 7 interfaces from `psr/http-message` v2.0
- **Full PSR-17 compliance** — implements all 6 factory interfaces from `psr/http-factory` v1.0
- **Optimized headers** — O(1) case-insensitive lookups via dual-array storage
- **Lazy URI composition** — `__toString()` result cached, invalidated on mutation
- **Efficient immutability** — shallow `clone` with shared stream references
- **Thin stream wrapper** — minimal overhead over native PHP resources
- **ServerRequestCreator** — builds requests from `$_SERVER` / `$_GET` / `$_POST` / `$_COOKIE` / `$_FILES`
- **PHPStan level 9** — fully statically analyzed

## Requirements

- PHP >= 8.4

## Installation

```bash
composer require ascetic-soft/psr7
```

## Quick Start

```php
use AsceticSoft\Psr7\HttpFactory;

$factory = new HttpFactory();

// Create a response
$response = $factory->createResponse(200);
$response->getBody()->write('Hello, World!');

// Create a request
$request = $factory->createRequest('GET', 'https://example.com/api/users');

// Create a URI
$uri = $factory->createUri('https://example.com/path?query=1#fragment');
```

## Usage

### Creating HTTP Messages Directly

```php
use AsceticSoft\Psr7\Request;
use AsceticSoft\Psr7\Response;
use AsceticSoft\Psr7\ServerRequest;
use AsceticSoft\Psr7\Stream;
use AsceticSoft\Psr7\Uri;

// Request
$request = new Request('POST', 'https://api.example.com/users', [
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer token123',
], Stream::create(json_encode(['name' => 'John'])));

// Response
$response = new Response(200, ['Content-Type' => 'text/html']);

// Server Request
$serverRequest = new ServerRequest('GET', '/api/users', serverParams: $_SERVER);

// URI
$uri = new Uri('https://example.com:8080/path?q=1#frag');
echo $uri->getHost();   // "example.com"
echo $uri->getPort();   // 8080
echo $uri->getPath();   // "/path"
```

### Using the HttpFactory (PSR-17)

The `HttpFactory` class implements all six PSR-17 factory interfaces in a single class:

```php
use AsceticSoft\Psr7\HttpFactory;

$factory = new HttpFactory();

// RequestFactoryInterface
$request = $factory->createRequest('GET', '/path');

// ResponseFactoryInterface
$response = $factory->createResponse(404, 'Not Found');

// ServerRequestFactoryInterface
$serverRequest = $factory->createServerRequest('POST', '/api', $_SERVER);

// StreamFactoryInterface
$stream = $factory->createStream('content');
$fileStream = $factory->createStreamFromFile('/path/to/file', 'r');

// UriFactoryInterface
$uri = $factory->createUri('https://example.com');

// UploadedFileFactoryInterface
$uploadedFile = $factory->createUploadedFile($stream, 1024, UPLOAD_ERR_OK, 'file.txt');
```

### Creating Server Requests from Globals

```php
use AsceticSoft\Psr7\ServerRequestCreator;

// Create from PHP superglobals ($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES)
$request = ServerRequestCreator::fromGlobals();

// Or pass custom arrays
$request = ServerRequestCreator::fromGlobals(
    server: $_SERVER,
    get: $_GET,
    post: $_POST,
    cookies: $_COOKIE,
    files: $_FILES,
);
```

### Using with Waypoint Router

```php
use AsceticSoft\Psr7\ServerRequestCreator;
use AsceticSoft\Waypoint\Router;

$router = new Router($container);

$router->get('/hello/{name}', function (string $name) use ($factory) {
    $response = $factory->createResponse();
    $response->getBody()->write("Hello, {$name}!");
    return $response;
});

$request = ServerRequestCreator::fromGlobals();
$response = $router->handle($request);
```

## Architecture

```
AsceticSoft\Psr7\
├── Stream                — StreamInterface (thin resource wrapper)
├── Uri                   — UriInterface (lazy __toString, cached composition)
├── Request               — RequestInterface (uses MessageTrait)
├── Response              — ResponseInterface (const reason phrases)
├── ServerRequest         — ServerRequestInterface (extends Request)
├── UploadedFile          — UploadedFileInterface
├── HttpFactory           — All 6 PSR-17 factory interfaces
├── ServerRequestCreator  — Creates ServerRequest from PHP globals
└── MessageTrait          — Shared header/protocol/body logic (trait)
```

### Optimization Details

| Component | Technique | Benefit |
|-----------|-----------|---------|
| **Headers** | Dual-array: `$headers` (original case) + `$headerNames` (lowercase → original) | O(1) case-insensitive lookup |
| **Uri** | Cached `__toString()`, invalidated on `with*()` | Avoid recomposition on repeated access |
| **Stream** | Direct resource wrapper, pre-computed capabilities | Minimal overhead, no unnecessary wrapping |
| **Immutability** | Shallow `clone`, shared stream references | Fast with*() operations |
| **Response** | `const PHRASES` array for status codes | Zero-cost reason phrase lookup |

## Development

```bash
make fix       # Auto-fix code style (PHP CS Fixer)
make cs-check  # Check code style (dry-run)
make stan      # Run PHPStan static analysis (level 9)
make test      # Run PHPUnit tests
make check     # Run all checks (cs-check + stan + test)
make all       # Fix code style, then run stan and tests
```

## License

MIT
