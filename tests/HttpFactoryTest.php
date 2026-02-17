<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\HttpFactory;
use AsceticSoft\Psr7\Request;
use AsceticSoft\Psr7\Response;
use AsceticSoft\Psr7\ServerRequest;
use AsceticSoft\Psr7\Stream;
use AsceticSoft\Psr7\UploadedFile;
use AsceticSoft\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

#[CoversClass(HttpFactory::class)]
final class HttpFactoryTest extends TestCase
{
    private HttpFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new HttpFactory();
    }

    public function testImplementsAllFactoryInterfaces(): void
    {
        self::assertInstanceOf(RequestFactoryInterface::class, $this->factory);
        self::assertInstanceOf(ResponseFactoryInterface::class, $this->factory);
        self::assertInstanceOf(ServerRequestFactoryInterface::class, $this->factory);
        self::assertInstanceOf(StreamFactoryInterface::class, $this->factory);
        self::assertInstanceOf(UploadedFileFactoryInterface::class, $this->factory);
        self::assertInstanceOf(UriFactoryInterface::class, $this->factory);
    }

    public function testCreateRequest(): void
    {
        $request = $this->factory->createRequest('GET', 'http://example.com');

        self::assertInstanceOf(Request::class, $request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://example.com', (string) $request->getUri());
    }

    public function testCreateRequestWithUriObject(): void
    {
        $uri = new Uri('http://example.com');
        $request = $this->factory->createRequest('POST', $uri);

        self::assertSame('POST', $request->getMethod());
    }

    public function testCreateResponse(): void
    {
        $response = $this->factory->createResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
    }

    public function testCreateResponseWithCode(): void
    {
        $response = $this->factory->createResponse(404, 'Not Found');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testCreateServerRequest(): void
    {
        $request = $this->factory->createServerRequest('GET', 'http://example.com', ['SERVER_NAME' => 'example.com']);

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame(['SERVER_NAME' => 'example.com'], $request->getServerParams());
    }

    public function testCreateStream(): void
    {
        $stream = $this->factory->createStream('Hello');

        self::assertInstanceOf(Stream::class, $stream);
        self::assertSame('Hello', (string) $stream);
    }

    public function testCreateStreamEmpty(): void
    {
        $stream = $this->factory->createStream();

        self::assertSame('', (string) $stream);
    }

    public function testCreateStreamFromFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'psr7');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'file content');

        try {
            $stream = $this->factory->createStreamFromFile($tmpFile, 'r');

            self::assertSame('file content', (string) $stream);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testCreateStreamFromFileInvalidThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->factory->createStreamFromFile('/nonexistent/file.txt');
    }

    public function testCreateStreamFromResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertNotFalse($resource);
        fwrite($resource, 'from resource');
        fseek($resource, 0);

        $stream = $this->factory->createStreamFromResource($resource);

        self::assertSame('from resource', (string) $stream);
    }

    public function testCreateUploadedFile(): void
    {
        $stream = Stream::create('uploaded content');
        $file = $this->factory->createUploadedFile($stream, 16, \UPLOAD_ERR_OK, 'test.txt', 'text/plain');

        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame(16, $file->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame('test.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
    }

    public function testCreateUploadedFileAutoSize(): void
    {
        $stream = Stream::create('12345');
        $file = $this->factory->createUploadedFile($stream);

        self::assertSame(5, $file->getSize());
    }

    public function testCreateUri(): void
    {
        $uri = $this->factory->createUri('http://example.com/path');

        self::assertInstanceOf(Uri::class, $uri);
        self::assertSame('http', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame('/path', $uri->getPath());
    }

    public function testCreateUriEmpty(): void
    {
        $uri = $this->factory->createUri();

        self::assertSame('', (string) $uri);
    }
}
