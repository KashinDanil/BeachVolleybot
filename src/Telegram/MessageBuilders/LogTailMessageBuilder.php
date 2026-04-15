<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Log\LogFileEntry;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class LogTailMessageBuilder extends AbstractLogMessageBuilder
{
    private const string FILE_IS_EMPTY = 'File is empty';

    public function buildLogTail(LogFileEntry $entry, ?string $tailContent): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildLogTailText($entry, $tailContent),
            $this->buildLogTailKeyboard($entry->filename),
        );
    }

    private function buildLogTailText(LogFileEntry $entry, ?string $tailContent): string
    {
        $header = $this->formatHeader()
            . $this->formatter->newLine()
            . $this->formatter->escape($this->entryLabel($entry));

        if (null === $tailContent) {
            return $header . $this->formatter->newLine() . $this->formatter->escape(self::FILE_IS_EMPTY);
        }

        return $header
            . $this->formatter->newLine()
            . $this->formatter->newLine()
            . $this->formatter->codeBlock($tailContent);
    }

    private function buildLogTailKeyboard(string $filename): array
    {
        return [
            $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::LogFile)->withFilename($filename)),
        ];
    }
}
