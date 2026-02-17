<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stream::class)]
final class StreamTest extends TestCase
{
    public function testCreateFromString(): void
    {
        $stream = Stream::create('Hello, World!');

        self::assertSame('Hello, World!', (string) $stream);
        self::assertSame(13, $stream->getSize());
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
    }

    public function testCreateEmpty(): void
    {
        $stream = Stream::create();

        self::assertSame('', (string) $stream);
        self::assertSame(0, $stream->getSize());
    }

    public function testConstructorRequiresResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        new Stream('not a resource');
    }

    public function testRead(): void
    {
        $stream = Stream::create('Hello');
        $stream->rewind();

        self::assertSame('Hel', $stream->read(3));
        self::assertSame('lo', $stream->read(10));
    }

    public function testWrite(): void
    {
        $stream = Stream::create();
        $bytes = $stream->write('test data');

        self::assertSame(9, $bytes);

        $stream->rewind();
        self::assertSame('test data', $stream->getContents());
    }

    public function testTell(): void
    {
        $stream = Stream::create('Hello');
        $stream->rewind();
        $stream->read(3);

        self::assertSame(3, $stream->tell());
    }

    public function testSeek(): void
    {
        $stream = Stream::create('Hello');
        $stream->seek(2);

        self::assertSame(2, $stream->tell());
        self::assertSame('llo', $stream->getContents());
    }

    public function testEof(): void
    {
        $stream = Stream::create('x');
        $stream->rewind();

        self::assertFalse($stream->eof());

        $stream->read(1);
        $stream->read(1);

        self::assertTrue($stream->eof());
    }

    public function testDetach(): void
    {
        $stream = Stream::create('test');
        $resource = $stream->detach();

        self::assertIsResource($resource);
        self::assertNull($stream->detach());
        self::assertNull($stream->getSize());
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertSame('', (string) $stream);
    }

    public function testClose(): void
    {
        $stream = Stream::create('test');
        $stream->close();

        self::assertSame('', (string) $stream);
        self::assertNull($stream->getSize());
    }

    public function testGetMetadata(): void
    {
        $stream = Stream::create('test');
        $meta = $stream->getMetadata();

        self::assertIsArray($meta);
        self::assertArrayHasKey('mode', $meta);
        self::assertIsString($stream->getMetadata('mode'));
        self::assertNull($stream->getMetadata('nonexistent'));
    }

    public function testGetMetadataAfterDetach(): void
    {
        $stream = Stream::create('test');
        $stream->detach();

        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('mode'));
    }

    public function testTellOnDetachedStream(): void
    {
        $stream = Stream::create('test');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->tell();
    }

    public function testSeekOnDetachedStream(): void
    {
        $stream = Stream::create('test');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->seek(0);
    }

    public function testReadOnDetachedStream(): void
    {
        $stream = Stream::create('test');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->read(1);
    }

    public function testWriteOnDetachedStream(): void
    {
        $stream = Stream::create('test');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->write('x');
    }

    public function testGetContentsOnDetachedStream(): void
    {
        $stream = Stream::create('test');
        $stream->detach();

        $this->expectException(\RuntimeException::class);
        $stream->getContents();
    }

    public function testReadOnlyStream(): void
    {
        $resource = fopen('php://temp', 'r');
        self::assertNotFalse($resource);

        $stream = new Stream($resource);

        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());

        $this->expectException(\RuntimeException::class);
        $stream->write('x');
    }

    public function testToStringRewinds(): void
    {
        $stream = Stream::create('Hello');
        $stream->seek(3);

        self::assertSame('Hello', (string) $stream);
    }

    public function testEofOnDetachedStream(): void
    {
        $stream = Stream::create('test');
        $stream->detach();

        self::assertTrue($stream->eof());
    }

    public function testGetSizeFromFstat(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertNotFalse($resource);
        fwrite($resource, 'hello world');

        $stream = new Stream($resource);

        self::assertSame(11, $stream->getSize());
    }

    public function testReadZeroLengthThrows(): void
    {
        $stream = Stream::create('test');

        $this->expectException(\RuntimeException::class);
        $stream->read(0);
    }
}
