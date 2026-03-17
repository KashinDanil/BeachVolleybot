<?php

declare(strict_types=1);

namespace BeachVolleybot\Queue;

use BeachVolleybot\Queue\Exceptions\QueueException;

final class FileLock
{
    /** @var resource|null */
    private mixed $fh = null;

    public function __construct(private readonly string $lockFile) {}

    public function __destruct()
    {
        if (null !== $this->fh) {
            fclose($this->fh);
        }
    }

    public function acquire(): void
    {
        if (null === $this->fh) {
            $this->fh = $this->init();
        }

        if (!flock($this->fh, LOCK_EX)) {
            throw new QueueException("Cannot acquire exclusive lock on: {$this->lockFile}");
        }
    }

    public function release(): void
    {
        flock($this->fh, LOCK_UN);
    }

    public function deleteFile(): void
    {
        @unlink($this->lockFile);
    }

    private function init(): mixed
    {
        $fh = @fopen($this->lockFile, 'cb');
        if (false === $fh) {
            throw new QueueException("Cannot open lock file: {$this->lockFile}");
        }

        return $fh;
    }
}