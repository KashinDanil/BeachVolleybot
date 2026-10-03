<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Telegram\Messages\MessageAddress;

readonly class GameMessageManager
{
    private GameMessageRepository $gameMessageRepository;

    public function __construct()
    {
        $this->gameMessageRepository = new GameMessageRepository(Connection::get());
    }

    public function addInlineMessage(int $gameId, string $inlineMessageId, string $inlineQueryId): void
    {
        $this->gameMessageRepository->addInlineMessage($gameId, $inlineMessageId, $inlineQueryId);
    }

    public function addChatMessage(int $gameId, int $chatId, int $messageId): void
    {
        $this->gameMessageRepository->addChatMessage($gameId, $chatId, $messageId);
    }

    /** @return list<GameMessageRecord> */
    public function findGameMessageRecordsByGameId(int $gameId): array
    {
        return array_map(GameMessageRecord::fromRow(...), $this->gameMessageRepository->findByGameId($gameId));
    }

    public function resolveGameIdByInlineMessageId(string $inlineMessageId): ?int
    {
        return $this->gameMessageRepository->findGameIdByInlineMessageId($inlineMessageId);
    }

    public function resolveGameIdByChatMessage(int $chatId, int $messageId): ?int
    {
        return $this->gameMessageRepository->findGameIdByChatMessage($chatId, $messageId);
    }

    public function resolveGameIdByMessageAddress(MessageAddress $messageAddress): ?int
    {
        if ($messageAddress->isInline()) {
            return $this->resolveGameIdByInlineMessageId($messageAddress->inlineMessageId);
        }

        return $this->resolveGameIdByChatMessage($messageAddress->chatId, $messageAddress->messageId);
    }
}
