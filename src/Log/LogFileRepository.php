<?php

declare(strict_types=1);

namespace BeachVolleybot\Log;

final class LogFileRepository
{
    public const string INVALID_FILENAME = 'Invalid filename';

    private const string FILENAME_PATTERN = '/^[a-zA-Z0-9._-]+$/';
    private const int    MAX_TAIL_LENGTH  = 3896; // Telegram 4096 char limit minus markup overhead

    public function __construct(
        private readonly string $logDir = BASE_LOG_DIR,
    ) {
    }

    public static function isValidFilename(string $filename): bool
    {
        return 1 === preg_match(self::FILENAME_PATTERN, $filename);
    }

    public function countAll(): int
    {
        return count($this->listPaths());
    }

    /** @return list<string> */
    private function listPaths(): array
    {
        return glob($this->logDir . '/*.log') ?: [];
    }

    /** @return list<LogFileEntry> */
    public function findPaginated(int $limit, int $offset): array
    {
        $paths = array_slice($this->listPaths(), $offset, $limit);

        return array_map(
            static fn(string $path) => new LogFileEntry(basename($path), filesize($path) ?: 0),
            $paths,
        );
    }

    public function find(string $filename): LogFileEntry
    {
        $filePath = $this->path($filename);
        $size = file_exists($filePath) ? (int)filesize($filePath) : 0;

        return new LogFileEntry($filename, $size);
    }

    public function path(string $filename): string
    {
        return $this->logDir . '/' . $filename;
    }

    public function exists(string $filename): bool
    {
        return file_exists($this->path($filename));
    }

    public function readTail(string $filename, int $lines = 10): ?string
    {
        $filePath = $this->path($filename);
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

        if (0 === $fileSize) {
            return null;
        }

        $readSize = min($fileSize, self::MAX_TAIL_LENGTH + 2048);
        $handle = fopen($filePath, 'rb');
        fseek($handle, $fileSize - $readSize);
        $buffer = fread($handle, $readSize);
        fclose($handle);

        $allLines = explode("\n", rtrim($buffer, "\n"));

        if ($fileSize > $readSize) {
            array_shift($allLines); // drop first partial line
        }

        $content = implode("\n", array_slice($allLines, -$lines));

        if (self::MAX_TAIL_LENGTH < mb_strlen($content)) {
            $content = mb_substr($content, -self::MAX_TAIL_LENGTH);
        }

        return $content;
    }

    public function clear(string $filename): void
    {
        $filePath = $this->path($filename);

        if (file_exists($filePath)) {
            file_put_contents($filePath, '');
        }
    }
}
