<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Log\LogFileEntry;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class LogsListMessageBuilder extends AbstractLogMessageBuilder
{
    /** @param list<LogFileEntry> $logEntries */
    public function buildLogsList(array $logEntries, KeyboardPagination $pagination): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildLogsListText($pagination),
            $this->buildLogsListKeyboard($logEntries, $pagination),
        );
    }

    private function buildLogsListText(KeyboardPagination $pagination): string
    {
        $header = $this->formatHeader();
        if (1 === $pagination->totalPages) {
            return $header;
        }

        return $header . $this->formatter->newLine() . $this->formatter->escape("Page $pagination->page of $pagination->totalPages");
    }

    private function buildLogsListKeyboard(array $logEntries, KeyboardPagination $pagination): array
    {
        $keyboard = [];

        foreach ($logEntries as $entry) {
            $keyboard[] = [$this->buildEntryButton($entry)];
        }

        $paginationRow = $this->paginationRow($pagination, AdminCallbackData::create(AdminCallbackAction::Logs));
        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::Settings));

        return $keyboard;
    }

    private function buildEntryButton(LogFileEntry $entry): array
    {
        return $this->buildActionButton(
            $this->entryLabel($entry),
            AdminCallbackData::create(AdminCallbackAction::LogFile)->withFilename($entry->filename),
        );
    }
}
