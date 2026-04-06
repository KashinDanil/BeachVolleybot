<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;

readonly class GameManager
{
    private GameRepository $gameRepository;

    private GamePlayerRepository $gamePlayerRepository;

    private GameSlotRepository $gameSlotRepository;

    private PlayerRepository $playerRepository;

    public function __construct()
    {
        $db = Connection::get();
        $this->gameRepository = new GameRepository($db);
        $this->gamePlayerRepository = new GamePlayerRepository($db);
        $this->gameSlotRepository = new GameSlotRepository($db);
        $this->playerRepository = new PlayerRepository($db);
    }

    public function createGame(NewGameData $data): int
    {
        $this->playerRepository->upsert(
            $data->playerRow['telegram_user_id'],
            $data->playerRow['first_name'],
            $data->playerRow['last_name'],
            $data->playerRow['username'],
        );

        $gameId = $this->gameRepository->create(
            $data->gameRow['title'],
            $data->playerRow['telegram_user_id'],
            $data->gameRow['inline_message_id'],
            $data->gameRow['inline_query_id'],
            $data->gameRow['location'],
        );

        $this->gamePlayerRepository->create(
            $gameId,
            $data->gamePlayerRow['telegram_user_id'],
            $data->gamePlayerRow['time'],
            $data->gamePlayerRow['volleyball'],
            $data->gamePlayerRow['net'],
        );

        $this->gameSlotRepository->create(
            $gameId,
            $data->slotRow['telegram_user_id'],
            $data->slotRow['position'],
        );

        return $gameId;
    }

    public function joinGame(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
    ): void {
        $this->playerRepository->upsert($telegramUserId, $firstName, $lastName, $username);

        if (null === $this->gamePlayerRepository->findByGamePlayer($gameId, $telegramUserId)) {
            $this->gamePlayerRepository->create($gameId, $telegramUserId);
        }

        $this->gameSlotRepository->create(
            $gameId,
            $telegramUserId,
            $this->gameSlotRepository->getNextPosition($gameId),
        );
    }

    public function leaveGame(int $gameId, int $telegramUserId): LeaveResult
    {
        $positions = $this->gameSlotRepository->findPositionsByPlayer($gameId, $telegramUserId);

        if (empty($positions)) {
            return LeaveResult::NotJoined;
        }

        $this->gameSlotRepository->delete($gameId, max($positions));

        if (1 === count($positions)) {
            $this->gamePlayerRepository->delete($gameId, $telegramUserId);
        }

        return LeaveResult::Left;
    }

    public function addNet(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gamePlayerRepository->incrementNet($gameId, $telegramUserId)) {
            return EquipmentResult::NotJoined;
        }

        return EquipmentResult::Added;
    }

    public function removeNet(int $gameId, int $telegramUserId): EquipmentResult
    {
        $netCount = $this->gamePlayerRepository->findNetCount($gameId, $telegramUserId);

        if (null === $netCount) {
            return EquipmentResult::NotJoined;
        }

        if (0 === $netCount) {
            return EquipmentResult::NoneLeft;
        }

        if (!$this->gamePlayerRepository->decrementNet($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        return EquipmentResult::Removed;
    }

    public function addVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gamePlayerRepository->incrementVolleyball($gameId, $telegramUserId)) {
            return EquipmentResult::NotJoined;
        }

        return EquipmentResult::Added;
    }

    public function removeVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        $volleyballCount = $this->gamePlayerRepository->findVolleyballCount($gameId, $telegramUserId);

        if (null === $volleyballCount) {
            return EquipmentResult::NotJoined;
        }

        if (0 === $volleyballCount) {
            return EquipmentResult::NoneLeft;
        }

        if (!$this->gamePlayerRepository->decrementVolleyball($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        return EquipmentResult::Removed;
    }

    public function setLocation(int $gameId, string $location): void
    {
        $this->gameRepository->updateLocation($gameId, $location);
    }

    public function joinWithTime(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        string $time,
    ): void {
        $this->playerRepository->upsert($telegramUserId, $firstName, $lastName, $username);

        if (!$this->gamePlayerRepository->updateTime($gameId, $telegramUserId, $time)) {
            $this->gamePlayerRepository->create($gameId, $telegramUserId, $time);

            $this->gameSlotRepository->create(
                $gameId,
                $telegramUserId,
                $this->gameSlotRepository->getNextPosition($gameId),
            );
        }
    }

    public function resolveGameIdByInlineMessageId(string $inlineMessageId): ?int
    {
        return $this->gameRepository->findGameIdByInlineMessageId($inlineMessageId);
    }

    public function resolveGameByInlineQueryId(string $inlineQueryId): ?GameLookupResult
    {
        $row = $this->gameRepository->findGameAndInlineMessageIdsByInlineQueryId($inlineQueryId);

        if (null === $row) {
            return null;
        }

        return new GameLookupResult(
            (int)$row['game_id'],
            (string)$row['inline_message_id'],
        );
    }
}
