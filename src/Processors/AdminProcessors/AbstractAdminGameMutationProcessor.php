<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;

abstract class AbstractAdminGameMutationProcessor extends AbstractAdminCallbackProcessor
{
    protected function refreshGameInlineMessage(int $gameId): void
    {
        $inlineMessageId = new GameRepository(Connection::get())->findInlineMessageIdByGameId($gameId);

        if (null === $inlineMessageId) {
            return;
        }

        $this->refreshInlineMessage($inlineMessageId);
    }
}
