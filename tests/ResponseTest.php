<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\Response;
use AsceticSoft\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testDefaultResponse(): void
    {
        $response = new Response();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.1', $response->getProtocolVersion());
    }

    public function testCustomStatusCode(): void
    {
        $response = new Response(404);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testCustomReasonPhrase(): void
    {
        $response = new Response(200, reasonPhrase: 'Custom');

        self::assertSame('Custom', $response->getReasonPhrase());
    }

    public function testWithStatus(): void
    {
        $response = new Response();
        $new = $response->withStatus(301);

        self::assertNotSame($response, $new);
        self::assertSame(301, $new->getStatusCode());
        self::assertSame('Moved Permanently', $new->getReasonPhrase());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testWithStatusAndCustomReason(): void
    {
        $response = new Response()->withStatus(200, 'All Good');

        self::assertSame('All Good', $response->getReasonPhrase());
    }

    public function testWithStatusInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Response()->withStatus(999);
    }

    public function testWithStatusBelow100Throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Response()->withStatus(99);
    }

    public function testWithHeaders(): void
    {
        $response = new Response(200, ['Content-Type' => 'text/html']);

        self::assertSame('text/html', $response->getHeaderLine('content-type'));
    }

    public function testWithBody(): void
    {
        $body = Stream::create('Hello');
        $response = new Response(200, body: $body);

        self::assertSame('Hello', (string) $response->getBody());
    }

    public function testAllCommonStatusCodes(): void
    {
        $codes = [100, 200, 201, 204, 301, 302, 304, 400, 401, 403, 404, 405, 500, 502, 503];

        foreach ($codes as $code) {
            $response = new Response($code);
            self::assertSame($code, $response->getStatusCode());
            self::assertNotEmpty($response->getReasonPhrase(), "Missing reason phrase for {$code}");
        }
    }

    public function testUnknownStatusCodeHasEmptyReason(): void
    {
        $response = new Response(599);

        self::assertSame(599, $response->getStatusCode());
        self::assertSame('', $response->getReasonPhrase());
    }

    public function testWithProtocolVersion(): void
    {
        $response = new Response();
        $new = $response->withProtocolVersion('2.0');

        self::assertSame('2.0', $new->getProtocolVersion());
    }

    public function testImmutability(): void
    {
        $response = new Response(200, ['X-Foo' => 'bar']);

        $new1 = $response->withStatus(404);
        $new2 = $response->withHeader('X-Baz', 'qux');
        $new3 = $response->withoutHeader('X-Foo');

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->hasHeader('X-Foo'));
        self::assertFalse($response->hasHeader('X-Baz'));

        self::assertSame(404, $new1->getStatusCode());
        self::assertSame('qux', $new2->getHeaderLine('X-Baz'));
        self::assertFalse($new3->hasHeader('X-Foo'));
    }
}
