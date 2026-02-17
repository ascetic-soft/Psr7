<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

final class UploadedFile implements UploadedFileInterface
{
    private ?StreamInterface $stream;

    private ?string $file;

    private ?int $size;

    private int $error;

    private ?string $clientFilename;

    private ?string $clientMediaType;

    private bool $moved = false;

    public function __construct(
        StreamInterface|string $streamOrFile,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ) {
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if ($error === \UPLOAD_ERR_OK) {
            if (\is_string($streamOrFile)) {
                $this->file = $streamOrFile;
                $this->stream = null;
            } else {
                $this->stream = $streamOrFile;
                $this->file = null;
            }
        } else {
            $this->stream = null;
            $this->file = null;
        }
    }

    public function getStream(): StreamInterface
    {
        $this->validateActive();

        if ($this->stream !== null) {
            return $this->stream;
        }

        if ($this->file !== null) {
            $resource = fopen($this->file, 'r');

            if ($resource === false) {
                throw new \RuntimeException(\sprintf('Unable to open file "%s"', $this->file));
            }

            return $this->stream = new Stream($resource);
        }

        throw new \RuntimeException('No stream or file available');
    }

    public function moveTo(string $targetPath): void
    {
        $this->validateActive();

        if ($targetPath === '') {
            throw new \InvalidArgumentException('Target path must be a non-empty string');
        }

        if ($this->file !== null) {
            $this->moved = \PHP_SAPI === 'cli'
                ? rename($this->file, $targetPath)
                : move_uploaded_file($this->file, $targetPath);
        } else {
            $stream = $this->getStream();
            $target = fopen($targetPath, 'w');

            if ($target === false) {
                throw new \RuntimeException(\sprintf('Unable to open target path "%s"', $targetPath));
            }

            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            while (!$stream->eof()) {
                fwrite($target, $stream->read(8192));
            }

            fclose($target);
            $this->moved = true;
        }

        if (!$this->moved) {
            throw new \RuntimeException(\sprintf('Unable to move uploaded file to "%s"', $targetPath));
        }
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    private function validateActive(): void
    {
        if ($this->error !== \UPLOAD_ERR_OK) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new \RuntimeException('Uploaded file has already been moved');
        }
    }
}
