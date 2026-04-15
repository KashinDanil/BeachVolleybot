<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Log;

use BeachVolleybot\Log\LogFileEntry;
use BeachVolleybot\Log\LogFileRepository;
use PHPUnit\Framework\TestCase;

final class LogFileRepositoryTest extends TestCase
{
    private string $testLogDir;

    private LogFileRepository $repository;

    public function testIsValidFilenameAcceptsAlphanumericWithDots(): void
    {
        $this->assertTrue(LogFileRepository::isValidFilename('app.log'));
    }

    public function testIsValidFilenameAcceptsDashesAndUnderscores(): void
    {
        $this->assertTrue(LogFileRepository::isValidFilename('user_actions-2024.log'));
    }

    // --- isValidFilename ---

    public function testIsValidFilenameRejectsPathTraversal(): void
    {
        $this->assertFalse(LogFileRepository::isValidFilename('../etc/passwd'));
    }

    public function testIsValidFilenameRejectsAbsolutePath(): void
    {
        $this->assertFalse(LogFileRepository::isValidFilename('/etc/passwd'));
    }

    public function testIsValidFilenameRejectsSpaces(): void
    {
        $this->assertFalse(LogFileRepository::isValidFilename('file name.log'));
    }

    public function testIsValidFilenameRejectsEmptyString(): void
    {
        $this->assertFalse(LogFileRepository::isValidFilename(''));
    }

    public function testIsValidFilenameRejectsShellCharacters(): void
    {
        $this->assertFalse(LogFileRepository::isValidFilename('file;rm -rf.log'));
    }

    public function testCountAllReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->repository->countAll());
    }

    public function testCountAllCountsOnlyLogFiles(): void
    {
        file_put_contents($this->testLogDir . '/app.log', 'content');
        file_put_contents($this->testLogDir . '/verbose.log', 'content');
        file_put_contents($this->testLogDir . '/not-a-log.txt', 'content');

        $this->assertSame(2, $this->repository->countAll());
    }

    // --- countAll ---

    public function testFindPaginatedReturnsLogFileEntries(): void
    {
        file_put_contents($this->testLogDir . '/app.log', 'hello');
        file_put_contents($this->testLogDir . '/verbose.log', 'world!');

        $entries = $this->repository->findPaginated(10, 0);

        $this->assertCount(2, $entries);
        $this->assertContainsOnlyInstancesOf(LogFileEntry::class, $entries);
    }

    public function testFindPaginatedRespectsLimitAndOffset(): void
    {
        file_put_contents($this->testLogDir . '/a.log', '1');
        file_put_contents($this->testLogDir . '/b.log', '22');
        file_put_contents($this->testLogDir . '/c.log', '333');

        $entries = $this->repository->findPaginated(1, 1);

        $this->assertCount(1, $entries);
    }

    // --- findPaginated ---

    public function testFindPaginatedReturnsCorrectSize(): void
    {
        $content = 'hello world';
        file_put_contents($this->testLogDir . '/app.log', $content);

        $entries = $this->repository->findPaginated(10, 0);

        $this->assertSame(strlen($content), $entries[0]->size);
    }

    public function testFindReturnsEntryForExistingFile(): void
    {
        file_put_contents($this->testLogDir . '/app.log', 'content');

        $entry = $this->repository->find('app.log');

        $this->assertSame('app.log', $entry->filename);
        $this->assertSame(7, $entry->size);
    }

    public function testFindReturnsZeroSizeForNonExistentFile(): void
    {
        $entry = $this->repository->find('missing.log');

        $this->assertSame('missing.log', $entry->filename);
        $this->assertSame(0, $entry->size);
    }

    // --- find ---

    public function testExistsReturnsTrueForExistingFile(): void
    {
        file_put_contents($this->testLogDir . '/app.log', 'data');

        $this->assertTrue($this->repository->exists('app.log'));
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->repository->exists('missing.log'));
    }

    // --- exists ---

    public function testReadTailReturnsLastNLines(): void
    {
        $lines = implode("\n", range(1, 20));
        file_put_contents($this->testLogDir . '/app.log', $lines);

        $tail = $this->repository->readTail('app.log', 5);

        $this->assertSame("16\n17\n18\n19\n20", $tail);
    }

    public function testReadTailReturnsNullForEmptyFile(): void
    {
        file_put_contents($this->testLogDir . '/empty.log', '');

        $this->assertNull($this->repository->readTail('empty.log'));
    }

    // --- readTail ---

    public function testReadTailReturnsNullForNonExistentFile(): void
    {
        $this->assertNull($this->repository->readTail('missing.log'));
    }

    public function testReadTailReturnsAllLinesWhenFewerThanRequested(): void
    {
        file_put_contents($this->testLogDir . '/small.log', "line1\nline2\nline3");

        $tail = $this->repository->readTail('small.log', 10);

        $this->assertSame("line1\nline2\nline3", $tail);
    }

    public function testClearEmptiesFileContent(): void
    {
        file_put_contents($this->testLogDir . '/app.log', 'some content');

        $this->repository->clear('app.log');

        $this->assertSame('', file_get_contents($this->testLogDir . '/app.log'));
    }

    public function testClearDoesNothingForNonExistentFile(): void
    {
        $this->repository->clear('missing.log');

        $this->assertFalse(file_exists($this->testLogDir . '/missing.log'));
    }

    // --- clear ---

    public function testPathConstructsFullPath(): void
    {
        $this->assertSame($this->testLogDir . '/app.log', $this->repository->path('app.log'));
    }

    protected function setUp(): void
    {
        $this->testLogDir = sys_get_temp_dir() . '/bvb_log_test_' . uniqid('', true);
        @mkdir($this->testLogDir, 0777, true);
        $this->repository = new LogFileRepository($this->testLogDir);
    }

    // --- path ---

    protected function tearDown(): void
    {
        $files = glob($this->testLogDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->testLogDir);
    }
}
