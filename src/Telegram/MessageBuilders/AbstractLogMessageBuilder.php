<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Common\FileSize;
use BeachVolleybot\Log\LogFileEntry;

abstract class AbstractLogMessageBuilder extends AbstractAdminMessageBuilder
{
    public const string HEADER_MESSAGE = 'Logs';

    protected function formatHeader(string $header = self::HEADER_MESSAGE): string
    {
        return parent::formatHeader($header);
    }

    protected function entryLabel(LogFileEntry $entry): string
    {
        return $entry->filename . ' (' . FileSize::format($entry->size) . ')';
    }

}
