<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;

abstract class AbstractAdminMessageBuilder extends AbstractMessageBuilder
{
    private const string BACK_LABEL = '↩ Back';
    private const string LABEL_PREV = '<< Prev';
    private const string LABEL_NEXT = 'Next >>';

    protected function formatHeader(string $header): string
    {
        return $this->formatter->bold($header);
    }

    /** @return list<array{text: string, callback_data: string}> */
    protected function backButtonRow(AdminCallbackData $callbackData): array
    {
        return [$this->buildActionButton(self::BACK_LABEL, $callbackData)];
    }

    /** @return ?list<array{text: string, callback_data: string}> */
    protected function paginationRow(KeyboardPagination $pagination, AdminCallbackData $callbackData): ?array
    {
        $row = [];
        $previousPage = $pagination->getPreviousPage();
        $nextPage = $pagination->getNextPage();

        if (null !== $previousPage) {
            $row[] = $this->buildActionButton(self::LABEL_PREV, $callbackData->withPage($previousPage));
        }

        if (null !== $nextPage) {
            $row[] = $this->buildActionButton(self::LABEL_NEXT, $callbackData->withPage($nextPage));
        }

        if (empty($row)) {
            return null;
        }

        return $row;
    }
}
