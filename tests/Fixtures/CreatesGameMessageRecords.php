<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Fixtures;

use BeachVolleybot\Game\GameMessageRecord;
use BeachVolleybot\Telegram\Messages\MessageAddress;
use DateTimeImmutable;

trait CreatesGameMessageRecords
{
    protected function inlineGameMessageRecord(
        string $inlineMessageId = 'msg_1',
        ?string $inlineQueryId = null,
        int $gameId = 1,
    ): GameMessageRecord {
        return new GameMessageRecord(
            gameId: $gameId,
            chatId: null,
            messageId: null,
            inlineMessageId: $inlineMessageId,
            inlineQueryId: $inlineQueryId,
            createdAt: new DateTimeImmutable('2099-12-01 10:00:00'),
        );
    }

    /**
     * @param list<GameMessageRecord> $gameMessages
     *
     * @return list<MessageAddress>
     */
    protected function messageAddresses(array $gameMessages): array
    {
        return array_map(static fn(GameMessageRecord $gameMessage): MessageAddress => $gameMessage->address(), $gameMessages);
    }
}
