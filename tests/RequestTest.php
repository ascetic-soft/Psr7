<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\Request;
use AsceticSoft\Psr7\Stream;
use AsceticSoft\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $request = new Request('GET', 'http://example.com/path');

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/path', $request->getUri()->getPath());
        self::assertSame('1.1', $request->getProtocolVersion());
    }

    public function testConstructionWithUriObject(): void
    {
        $uri = new Uri('http://example.com');
        $request = new Request('POST', $uri);

        self::assertSame($uri, $request->getUri());
    }

    public function testConstructionSetsHostHeader(): void
    {
        $request = new Request('GET', 'http://example.com');

        self::assertTrue($request->hasHeader('host'));
        self::assertSame('example.com', $request->getHeaderLine('host'));
    }

    public function testConstructionSetsHostWithPort(): void
    {
        $request = new Request('GET', 'http://example.com:8080');

        self::assertSame('example.com:8080', $request->getHeaderLine('host'));
    }

    public function testConstructionPreservesExistingHost(): void
    {
        $request = new Request('GET', 'http://example.com', ['Host' => 'custom.com']);

        self::assertSame('custom.com', $request->getHeaderLine('host'));
    }

    public function testWithMethod(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withMethod('POST');

        self::assertNotSame($request, $new);
        self::assertSame('POST', $new->getMethod());
        self::assertSame('GET', $request->getMethod());
    }

    public function testWithMethodReturnsSameForIdentical(): void
    {
        $request = new Request('GET', '/');
        self::assertSame($request, $request->withMethod('GET'));
    }

    public function testWithUri(): void
    {
        $request = new Request('GET', 'http://example.com');
        $newUri = new Uri('http://other.com/path');
        $new = $request->withUri($newUri);

        self::assertSame($newUri, $new->getUri());
        self::assertSame('other.com', $new->getHeaderLine('host'));
    }

    public function testWithUriPreserveHost(): void
    {
        $request = new Request('GET', 'http://example.com');
        $newUri = new Uri('http://other.com');
        $new = $request->withUri($newUri, true);

        self::assertSame('example.com', $new->getHeaderLine('host'));
    }

    public function testWithUriPreserveHostUpdatesWhenMissing(): void
    {
        $request = new Request('GET', '/path');
        $newUri = new Uri('http://example.com');
        $new = $request->withUri($newUri, true);

        self::assertSame('example.com', $new->getHeaderLine('host'));
    }

    public function testGetRequestTarget(): void
    {
        $request = new Request('GET', 'http://example.com/path?query=1');

        self::assertSame('/path?query=1', $request->getRequestTarget());
    }

    public function testGetRequestTargetDefaultsToSlash(): void
    {
        $request = new Request('GET', '');

        self::assertSame('/', $request->getRequestTarget());
    }

    public function testWithRequestTarget(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withRequestTarget('*');

        self::assertSame('*', $new->getRequestTarget());
    }

    public function testWithRequestTargetReturnsSameForIdentical(): void
    {
        $request = new Request('GET', '/');
        $target = $request->withRequestTarget('');
        self::assertSame($request, $target);
    }

    public function testWithBody(): void
    {
        $request = new Request('GET', '/');
        $body = Stream::create('test body');
        $new = $request->withBody($body);

        self::assertNotSame($request, $new);
        self::assertSame('test body', (string) $new->getBody());
    }

    public function testWithProtocolVersion(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withProtocolVersion('2.0');

        self::assertSame('2.0', $new->getProtocolVersion());
    }

    public function testHeaders(): void
    {
        $request = new Request('GET', '/', ['X-Custom' => 'value']);

        self::assertTrue($request->hasHeader('x-custom'));
        self::assertSame(['value'], $request->getHeader('X-Custom'));
        self::assertSame('value', $request->getHeaderLine('x-custom'));
    }

    public function testWithHeader(): void
    {
        $request = new Request('GET', '/');
        $new = $request->withHeader('X-Foo', 'bar');

        self::assertSame('bar', $new->getHeaderLine('x-foo'));
        self::assertFalse($request->hasHeader('x-foo'));
    }

    public function testWithAddedHeader(): void
    {
        $request = new Request('GET', '/', ['X-Foo' => 'bar']);
        $new = $request->withAddedHeader('X-Foo', 'baz');

        self::assertSame('bar, baz', $new->getHeaderLine('x-foo'));
    }

    public function testWithoutHeader(): void
    {
        $request = new Request('GET', '/', ['X-Foo' => 'bar']);
        $new = $request->withoutHeader('x-foo');

        self::assertFalse($new->hasHeader('x-foo'));
    }

    public function testWithoutHeaderReturnsSameWhenMissing(): void
    {
        $request = new Request('GET', '/');
        self::assertSame($request, $request->withoutHeader('nonexistent'));
    }

    public function testGetHeaderReturnsEmptyArrayForMissingHeader(): void
    {
        $request = new Request('GET', '/');

        self::assertSame([], $request->getHeader('nonexistent'));
        self::assertSame('', $request->getHeaderLine('nonexistent'));
    }

    public function testConstructionWithBody(): void
    {
        $body = Stream::create('hello');
        $request = new Request('POST', '/', [], $body);

        self::assertSame('hello', (string) $request->getBody());
    }

    public function testGetBodyCreatesDefaultStream(): void
    {
        $request = new Request('GET', '/');

        self::assertSame('', (string) $request->getBody());
    }

    public function testHeaderCasePreserved(): void
    {
        $request = new Request('GET', '/', ['X-Custom-Header' => 'val']);
        $headers = $request->getHeaders();

        self::assertArrayHasKey('X-Custom-Header', $headers);
    }

    public function testWithHeaderReplacesExistingCase(): void
    {
        $request = new Request('GET', '/', ['X-Foo' => 'old']);
        $new = $request->withHeader('x-foo', 'new');

        $headers = $new->getHeaders();
        self::assertArrayNotHasKey('X-Foo', $headers);
        self::assertArrayHasKey('x-foo', $headers);
        self::assertSame(['new'], $headers['x-foo']);
    }
}
