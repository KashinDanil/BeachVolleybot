<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Log\LogFileEntry;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class LogFileActionsMessageBuilder extends AbstractLogMessageBuilder
{
    public function buildLogFileActions(LogFileEntry $entry): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildLogFileActionsText($entry),
            $this->buildLogFileActionsKeyboard($entry),
        );
    }

    private function buildLogFileActionsText(LogFileEntry $entry): string
    {
        return $this->formatHeader()
            . $this->formatter->newLine()
            . $this->formatter->escape($this->entryLabel($entry));
    }

    private function buildLogFileActionsKeyboard(LogFileEntry $entry): array
    {
        $fileCallback = static fn(AdminCallbackAction $action) => AdminCallbackData::create($action)->withFilename($entry->filename);

        return [
            [
                $this->buildActionButton('Get', $fileCallback(AdminCallbackAction::LogGet)),
                $this->buildActionButton('Tail', $fileCallback(AdminCallbackAction::LogTail)),
                $this->buildActionButton('Clear', $fileCallback(AdminCallbackAction::LogClear)),
            ],
            $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::Logs)->withPage(1)),
        ];
    }
}
