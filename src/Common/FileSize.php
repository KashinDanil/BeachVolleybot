<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

final class FileSize
{
    public static function format(int $bytes): string
    {
        if (1048576 <= $bytes) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if (1024 <= $bytes) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
