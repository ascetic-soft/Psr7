<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\ServerRequest;
use AsceticSoft\Psr7\Stream;
use AsceticSoft\Psr7\UploadedFile;
use AsceticSoft\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerRequest::class)]
final class ServerRequestTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/path');

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/path', $request->getUri()->getPath());
        self::assertSame([], $request->getServerParams());
        self::assertSame([], $request->getCookieParams());
        self::assertSame([], $request->getQueryParams());
        self::assertSame([], $request->getUploadedFiles());
        self::assertNull($request->getParsedBody());
        self::assertSame([], $request->getAttributes());
    }

    public function testWithServerParams(): void
    {
        $params = ['REQUEST_METHOD' => 'GET', 'SERVER_NAME' => 'example.com'];
        $request = new ServerRequest('GET', '/', serverParams: $params);

        self::assertSame($params, $request->getServerParams());
    }

    public function testWithCookieParams(): void
    {
        $request = new ServerRequest('GET', '/');
        $cookies = ['session' => 'abc123'];
        $new = $request->withCookieParams($cookies);

        self::assertNotSame($request, $new);
        self::assertSame($cookies, $new->getCookieParams());
        self::assertSame([], $request->getCookieParams());
    }

    public function testWithQueryParams(): void
    {
        $request = new ServerRequest('GET', '/');
        $query = ['page' => '1', 'limit' => '10'];
        $new = $request->withQueryParams($query);

        self::assertSame($query, $new->getQueryParams());
        self::assertSame([], $request->getQueryParams());
    }

    public function testWithUploadedFiles(): void
    {
        $request = new ServerRequest('POST', '/');
        $file = new UploadedFile(Stream::create('file content'), 12, \UPLOAD_ERR_OK, 'test.txt', 'text/plain');
        $new = $request->withUploadedFiles(['file' => $file]);

        self::assertCount(1, $new->getUploadedFiles());
        self::assertSame([], $request->getUploadedFiles());
    }

    public function testWithParsedBody(): void
    {
        $request = new ServerRequest('POST', '/');

        $arrayBody = ['key' => 'value'];
        $new = $request->withParsedBody($arrayBody);
        self::assertSame($arrayBody, $new->getParsedBody());

        $nullBody = $request->withParsedBody(null);
        self::assertNull($nullBody->getParsedBody());

        $objectBody = $request->withParsedBody((object) ['key' => 'value']);
        self::assertIsObject($objectBody->getParsedBody());
    }

    public function testWithParsedBodyInvalidThrows(): void
    {
        $request = new ServerRequest('POST', '/');

        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        $request->withParsedBody('invalid string');
    }

    public function testAttributes(): void
    {
        $request = new ServerRequest('GET', '/');

        $new = $request->withAttribute('key', 'value');
        self::assertSame('value', $new->getAttribute('key'));
        self::assertNull($request->getAttribute('key'));
    }

    public function testGetAttributeDefault(): void
    {
        $request = new ServerRequest('GET', '/');

        self::assertNull($request->getAttribute('missing'));
        self::assertSame('default', $request->getAttribute('missing', 'default'));
    }

    public function testWithoutAttribute(): void
    {
        $request = new ServerRequest('GET', '/')
            ->withAttribute('key', 'value');

        $new = $request->withoutAttribute('key');

        self::assertNull($new->getAttribute('key'));
        self::assertSame('value', $request->getAttribute('key'));
    }

    public function testWithoutAttributeReturnsSameWhenMissing(): void
    {
        $request = new ServerRequest('GET', '/');
        self::assertSame($request, $request->withoutAttribute('nonexistent'));
    }

    public function testGetAttributes(): void
    {
        $request = new ServerRequest('GET', '/')
            ->withAttribute('a', 1)
            ->withAttribute('b', 2);

        self::assertSame(['a' => 1, 'b' => 2], $request->getAttributes());
    }

    public function testMultipleAttributeModifications(): void
    {
        $request = new ServerRequest('GET', '/');

        $r1 = $request->withAttribute('id', 42);
        $r2 = $r1->withAttribute('name', 'test');
        $r3 = $r2->withoutAttribute('id');

        self::assertSame(42, $r1->getAttribute('id'));
        self::assertNull($r1->getAttribute('name'));

        self::assertSame(42, $r2->getAttribute('id'));
        self::assertSame('test', $r2->getAttribute('name'));

        self::assertNull($r3->getAttribute('id'));
        self::assertSame('test', $r3->getAttribute('name'));
    }

    public function testInheritsRequestFunctionality(): void
    {
        $request = new ServerRequest('POST', 'http://example.com/api', ['Content-Type' => 'application/json']);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('example.com', $request->getHeaderLine('host'));
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
    }

    public function testWithUriObject(): void
    {
        $uri = new Uri('http://example.com:8080/path');
        $request = new ServerRequest('GET', $uri);

        self::assertSame($uri, $request->getUri());
        self::assertSame('example.com:8080', $request->getHeaderLine('host'));
    }
}
