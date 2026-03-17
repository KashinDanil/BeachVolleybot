<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Queue;

use BeachVolleybot\Queue\Exceptions\QueueException;
use BeachVolleybot\Queue\FileLock;
use PHPUnit\Framework\TestCase;

final class FileLockTest extends TestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        $this->lockFile = sys_get_temp_dir() . '/bvb_lock_test_' . uniqid(more_entropy: true) . '.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->lockFile);
    }

    public function testAcquireCreatesLockFile(): void
    {
        $lock = new FileLock($this->lockFile);
        $lock->acquire();
        $lock->release();

        $this->assertFileExists($this->lockFile);
    }

    public function testReleaseAllowsSubsequentAcquire(): void
    {
        $lock = new FileLock($this->lockFile);
        $lock->acquire();
        $lock->release();

        $fh      = fopen($this->lockFile, 'cb');
        $success = flock($fh, LOCK_EX | LOCK_NB);
        if ($success) {
            flock($fh, LOCK_UN);
        }
        fclose($fh);

        $this->assertTrue($success);
    }

    public function testDeleteFileRemovesLockFile(): void
    {
        $lock = new FileLock($this->lockFile);
        $lock->acquire();
        $lock->release();
        $lock->deleteFile();

        $this->assertFileDoesNotExist($this->lockFile);
    }

    public function testAcquireThrowsWhenDirectoryDoesNotExist(): void
    {
        $lock = new FileLock('/nonexistent_dir_bvb/queue.lock');

        $this->expectException(QueueException::class);

        $lock->acquire();
    }

    public function testAcquireIsIdempotentOnSameInstance(): void
    {
        $lock = new FileLock($this->lockFile);
        $lock->acquire();
        $lock->acquire(); // must not throw or deadlock — re-entrant on same fh

        $lock->release();
        $this->assertTrue(true);
    }
}