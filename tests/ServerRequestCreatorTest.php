<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\ServerRequest;
use AsceticSoft\Psr7\ServerRequestCreator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

#[CoversClass(ServerRequestCreator::class)]
final class ServerRequestCreatorTest extends TestCase
{
    public function testBasicRequest(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/path?q=1',
                'SERVER_NAME' => 'example.com',
                'SERVER_PORT' => '80',
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'HTTP_HOST' => 'example.com',
            ],
            get: ['q' => '1'],
            post: [],
            cookies: ['session' => 'abc'],
            files: [],
        );

        self::assertInstanceOf(ServerRequest::class, $request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/path', $request->getUri()->getPath());
        self::assertSame('q=1', $request->getUri()->getQuery());
        self::assertSame('http', $request->getUri()->getScheme());
        self::assertSame('example.com', $request->getUri()->getHost());
        self::assertSame(['q' => '1'], $request->getQueryParams());
        self::assertSame(['session' => 'abc'], $request->getCookieParams());
        self::assertSame('1.1', $request->getProtocolVersion());
    }

    public function testHttpsDetection(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTPS' => 'on',
                'HTTP_HOST' => 'secure.example.com',
            ],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('https', $request->getUri()->getScheme());
    }

    public function testHostWithPort(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'example.com:8080',
            ],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('example.com', $request->getUri()->getHost());
        self::assertSame(8080, $request->getUri()->getPort());
    }

    public function testFallbackToServerName(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SERVER_NAME' => 'fallback.com',
                'SERVER_PORT' => '9090',
            ],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('fallback.com', $request->getUri()->getHost());
        self::assertSame(9090, $request->getUri()->getPort());
    }

    public function testExtractsHeaders(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'example.com',
                'HTTP_ACCEPT' => 'text/html',
                'HTTP_X_CUSTOM' => 'value',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => '42',
            ],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('text/html', $request->getHeaderLine('accept'));
        self::assertSame('value', $request->getHeaderLine('x-custom'));
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
        self::assertSame('42', $request->getHeaderLine('content-length'));
    }

    public function testProtocolVersion(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'SERVER_PROTOCOL' => 'HTTP/2.0',
            ],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('2.0', $request->getProtocolVersion());
    }

    public function testDefaultProtocolVersion(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
            ],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('1.1', $request->getProtocolVersion());
    }

    public function testFileUploadNormalization(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'psr7');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'upload content');

        try {
            $request = ServerRequestCreator::fromGlobals(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
                get: [],
                post: [],
                cookies: [],
                files: [
                    'avatar' => [
                        'tmp_name' => $tmpFile,
                        'size' => 14,
                        'error' => \UPLOAD_ERR_OK,
                        'name' => 'avatar.jpg',
                        'type' => 'image/jpeg',
                    ],
                ],
            );

            $files = $request->getUploadedFiles();
            self::assertArrayHasKey('avatar', $files);

            $file = $files['avatar'];
            self::assertInstanceOf(UploadedFileInterface::class, $file);
            self::assertSame('avatar.jpg', $file->getClientFilename());
            self::assertSame('image/jpeg', $file->getClientMediaType());
            self::assertSame(14, $file->getSize());
            self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testMultiFileUploadNormalization(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            get: [],
            post: [],
            cookies: [],
            files: [
                'docs' => [
                    'tmp_name' => ['/tmp/a', '/tmp/b'],
                    'size' => [100, 200],
                    'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_OK],
                    'name' => ['doc1.pdf', 'doc2.pdf'],
                    'type' => ['application/pdf', 'application/pdf'],
                ],
            ],
        );

        $files = $request->getUploadedFiles();
        self::assertArrayHasKey('docs', $files);
        self::assertIsArray($files['docs']);
        self::assertCount(2, $files['docs']);

        self::assertInstanceOf(UploadedFileInterface::class, $files['docs'][0]);
        self::assertSame('doc1.pdf', $files['docs'][0]->getClientFilename());

        self::assertInstanceOf(UploadedFileInterface::class, $files['docs'][1]);
        self::assertSame('doc2.pdf', $files['docs'][1]->getClientFilename());
    }

    public function testPostParsedBody(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            get: [],
            post: ['username' => 'john', 'password' => 'secret'],
            cookies: [],
            files: [],
        );

        $body = $request->getParsedBody();
        self::assertIsArray($body);
        self::assertSame('john', $body['username']);
    }

    public function testQueryStringFromServer(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/path?from_uri=1',
                'QUERY_STRING' => 'from_server=1',
            ],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('from_server=1', $request->getUri()->getQuery());
    }

    public function testDefaultMethod(): void
    {
        $request = ServerRequestCreator::fromGlobals(
            server: ['REQUEST_URI' => '/'],
            get: [],
            post: [],
            cookies: [],
            files: [],
        );

        self::assertSame('GET', $request->getMethod());
    }
}
