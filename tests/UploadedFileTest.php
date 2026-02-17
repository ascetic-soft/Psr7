<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\Stream;
use AsceticSoft\Psr7\UploadedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadedFile::class)]
final class UploadedFileTest extends TestCase
{
    public function testConstructFromStream(): void
    {
        $stream = Stream::create('file content');
        $file = new UploadedFile($stream, 12, \UPLOAD_ERR_OK, 'test.txt', 'text/plain');

        self::assertSame(12, $file->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame('test.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
        self::assertSame($stream, $file->getStream());
    }

    public function testConstructFromFilePath(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'psr7');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'test content');

        try {
            $file = new UploadedFile($tmpFile, 12, \UPLOAD_ERR_OK, 'upload.txt');

            $stream = $file->getStream();
            self::assertSame('test content', (string) $stream);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetStreamThrowsOnError(): void
    {
        $file = new UploadedFile('', null, \UPLOAD_ERR_INI_SIZE, 'test.txt');

        $this->expectException(\RuntimeException::class);
        $file->getStream();
    }

    public function testMoveToFromStream(): void
    {
        $stream = Stream::create('moveable content');
        $file = new UploadedFile($stream, 16, \UPLOAD_ERR_OK);

        $targetPath = tempnam(sys_get_temp_dir(), 'psr7_move');
        self::assertNotFalse($targetPath);

        try {
            $file->moveTo($targetPath);

            self::assertSame('moveable content', file_get_contents($targetPath));
        } finally {
            @unlink($targetPath);
        }
    }

    public function testMoveToFromFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'psr7_src');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'source content');

        $targetPath = tempnam(sys_get_temp_dir(), 'psr7_dst');
        self::assertNotFalse($targetPath);

        try {
            $file = new UploadedFile($tmpFile, 14, \UPLOAD_ERR_OK);
            $file->moveTo($targetPath);

            self::assertSame('source content', file_get_contents($targetPath));
        } finally {
            @unlink($targetPath);
            @unlink($tmpFile);
        }
    }

    public function testMoveToThrowsOnSecondCall(): void
    {
        $stream = Stream::create('content');
        $file = new UploadedFile($stream, 7, \UPLOAD_ERR_OK);

        $target1 = tempnam(sys_get_temp_dir(), 'psr7');
        self::assertNotFalse($target1);

        try {
            $file->moveTo($target1);

            $this->expectException(\RuntimeException::class);
            $file->moveTo($target1);
        } finally {
            @unlink($target1);
        }
    }

    public function testMoveToEmptyPathThrows(): void
    {
        $file = new UploadedFile(Stream::create('x'), 1, \UPLOAD_ERR_OK);

        $this->expectException(\InvalidArgumentException::class);
        $file->moveTo('');
    }

    public function testGetStreamAfterMoveThrows(): void
    {
        $stream = Stream::create('content');
        $file = new UploadedFile($stream, 7, \UPLOAD_ERR_OK);

        $target = tempnam(sys_get_temp_dir(), 'psr7');
        self::assertNotFalse($target);

        try {
            $file->moveTo($target);

            $this->expectException(\RuntimeException::class);
            $file->getStream();
        } finally {
            @unlink($target);
        }
    }

    public function testNullMetadata(): void
    {
        $file = new UploadedFile(Stream::create(''), null, \UPLOAD_ERR_OK);

        self::assertNull($file->getSize());
        self::assertNull($file->getClientFilename());
        self::assertNull($file->getClientMediaType());
    }

    public function testUploadErrorCodes(): void
    {
        $errors = [
            \UPLOAD_ERR_INI_SIZE,
            \UPLOAD_ERR_FORM_SIZE,
            \UPLOAD_ERR_PARTIAL,
            \UPLOAD_ERR_NO_FILE,
            \UPLOAD_ERR_NO_TMP_DIR,
            \UPLOAD_ERR_CANT_WRITE,
            \UPLOAD_ERR_EXTENSION,
        ];

        foreach ($errors as $error) {
            $file = new UploadedFile('', null, $error);

            self::assertSame($error, $file->getError());

            try {
                $file->getStream();
                self::fail("Expected RuntimeException for error code {$error}");
            } catch (\RuntimeException) {
                // Expected.
            }
        }
    }
}
