# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-17

### Added

- Full PSR-7 (`psr/http-message` v2.0) implementation:
  - `Stream` — StreamInterface with efficient resource wrapping
  - `Uri` — UriInterface with lazy `__toString()` and cached composition
  - `Request` — RequestInterface with MessageTrait
  - `Response` — ResponseInterface with const reason phrase lookup
  - `ServerRequest` — ServerRequestInterface extending Request
  - `UploadedFile` — UploadedFileInterface with file and stream support
- Full PSR-17 (`psr/http-factory` v1.0) implementation:
  - `HttpFactory` — all 6 factory interfaces in a single class
- `ServerRequestCreator::fromGlobals()` for creating requests from PHP superglobals
- `MessageTrait` with optimized dual-array header storage for O(1) case-insensitive lookups
- PHPStan level 9 compliance
- Comprehensive PHPUnit test suite (141 tests, 330 assertions)
