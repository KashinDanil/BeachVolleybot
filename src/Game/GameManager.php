<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Telegram\Messages\Targets\ChatGameMessageTarget;
use BeachVolleybot\Telegram\Messages\Targets\GameMessageTarget;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
use DateTimeImmutable;

readonly class GameManager
{
    protected GameRepository $gameRepository;

    protected GameMessageRepository $gameMessageRepository;

    protected GameUserRepository $gameUserRepository;

    protected GameSlotRepository $gameSlotRepository;

    protected UserRepository $userRepository;

    public function __construct()
    {
        $db = Connection::get();
        $this->gameRepository = new GameRepository($db);
        $this->gameMessageRepository = new GameMessageRepository($db);
        $this->gameUserRepository = new GameUserRepository($db);
        $this->gameSlotRepository = new GameSlotRepository($db);
        $this->userRepository = new UserRepository($db);
    }

    public function createGame(NewGameData $data): int
    {
        $this->userRepository->upsert(
            $data->telegramUserId,
            $data->firstName,
            $data->lastName,
            $data->username,
        );

        $gameId = $this->gameRepository->create(
            $data->title,
            $data->telegramUserId,
            $data->gameKey,
            ParsedTitle::parse($data->title, $data->createdAt),
        );

        $this->gameUserRepository->create(
            $gameId,
            $data->telegramUserId,
            TimeExtractor::extract($data->title),
            NewGameData::INITIAL_VOLLEYBALL,
            NewGameData::INITIAL_NET,
        );

        $this->gameSlotRepository->create($gameId, $data->telegramUserId, NewGameData::INITIAL_POSITION);

        return $gameId;
    }

    public function joinGame(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
    ): void {
        $this->userRepository->upsert($telegramUserId, $firstName, $lastName, $username);
        $this->ensureGameUser($gameId, $telegramUserId);
        $this->addSlot($gameId, $telegramUserId);
    }

    public function leaveGame(int $gameId, int $telegramUserId): LeaveResult
    {
        $positions = $this->gameSlotRepository->findPositionsByUser($gameId, $telegramUserId);

        if (empty($positions)) {
            return LeaveResult::NotJoined;
        }

        $this->gameSlotRepository->delete($gameId, max($positions));

        if (1 === count($positions)) {
            $this->gameUserRepository->delete($gameId, $telegramUserId);
        }

        return LeaveResult::Left;
    }

    public function addNet(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
    ): EquipmentResult {
        $this->ensureUserInGame($gameId, $telegramUserId, $firstName, $lastName, $username);

        return $this->incrementNet($gameId, $telegramUserId);
    }

    public function removeNet(int $gameId, int $telegramUserId): EquipmentResult
    {
        $netCount = $this->gameUserRepository->findNetCount($gameId, $telegramUserId);

        if (null === $netCount) {
            return EquipmentResult::NotJoined;
        }

        if (0 === $netCount) {
            return EquipmentResult::NoneLeft;
        }

        if (!$this->gameUserRepository->decrementNet($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        $this->recalculateGameTime($gameId);

        return EquipmentResult::Removed;
    }

    public function addVolleyball(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
    ): EquipmentResult {
        $this->ensureUserInGame($gameId, $telegramUserId, $firstName, $lastName, $username);

        return $this->incrementVolleyball($gameId, $telegramUserId);
    }

    public function removeVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        $volleyballCount = $this->gameUserRepository->findVolleyballCount($gameId, $telegramUserId);

        if (null === $volleyballCount) {
            return EquipmentResult::NotJoined;
        }

        if (0 === $volleyballCount) {
            return EquipmentResult::NoneLeft;
        }

        if (!$this->gameUserRepository->decrementVolleyball($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        return EquipmentResult::Removed;
    }

    public function setLocation(int $gameId, float $latitude, float $longitude): string
    {
        $location = sprintf('%s,%s', $latitude, $longitude);
        $this->gameRepository->updateLocation($gameId, $location);

        return $location;
    }

    public function removeLocation(int $gameId): void
    {
        $this->gameRepository->updateLocation($gameId, null);
    }

    public function setUserTime(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        string $time,
    ): void {
        $this->ensureUserInGame($gameId, $telegramUserId, $firstName, $lastName, $username);

        $this->gameUserRepository->updateTime($gameId, $telegramUserId, $time);

        $this->recalculateGameTime($gameId);
    }

    public function changeTitle(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        string $newTitle,
    ): void {
        $normalizedTitle = TimeExtractor::normalize($newTitle);
        $proposedTime = TimeExtractor::extract($normalizedTitle);
        if (null === $proposedTime) {
            return;
        }

        $this->gameRepository->updateTitle($gameId, $normalizedTitle, $this->parseTitle($gameId, $normalizedTitle));
        $this->setUserTime($gameId, $telegramUserId, $firstName, $lastName, $username, $proposedTime);
    }

    public function isUserInGame(int $gameId, int $telegramUserId): bool
    {
        return $this->gameUserRepository->exists($gameId, $telegramUserId);
    }

    public function addInlineMessage(int $gameId, string $inlineMessageId): void
    {
        $this->gameMessageRepository->addInlineMessage($gameId, $inlineMessageId);
    }

    public function addChatMessage(int $gameId, int $chatId, int $messageId): void
    {
        $this->gameMessageRepository->addChatMessage($gameId, $chatId, $messageId);
    }

    public function resolveGameIdByGameKey(string $gameKey): ?int
    {
        return $this->gameRepository->findGameIdByGameKey($gameKey);
    }

    public function resolveGameIdByInlineMessageId(string $inlineMessageId): ?int
    {
        return $this->gameMessageRepository->findGameIdByInlineMessageId($inlineMessageId);
    }

    public function resolveGameIdByChatMessage(int $chatId, int $messageId): ?int
    {
        return $this->gameMessageRepository->findGameIdByChatMessage($chatId, $messageId);
    }

    public function resolveGameIdByTarget(GameMessageTarget $target): ?int
    {
        return match (true) {
            $target instanceof InlineGameMessageTarget => $this->resolveGameIdByInlineMessageId($target->inlineMessageId),
            $target instanceof ChatGameMessageTarget => $this->resolveGameIdByChatMessage($target->chatId, $target->messageId),
        };
    }

    public function findGameRecordByGameKey(string $gameKey): ?GameRecord
    {
        return $this->buildGameRecord($this->gameRepository->findByGameKey($gameKey));
    }

    public function findGameRecordById(int $gameId): ?GameRecord
    {
        return $this->buildGameRecord($this->gameRepository->findById($gameId));
    }

    private function buildGameRecord(?array $row): ?GameRecord
    {
        if (null === $row) {
            return null;
        }

        return new GameRecord(
            (int)$row['game_id'],
            (string)$row['game_key'],
            (int)$row['created_by'],
            (string)$row['title'],
            new DateTimeImmutable((string)$row['created_at']),
            new DateTimeImmutable((string)$row['kickoff_at']),
        );
    }

    protected function incrementNet(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gameUserRepository->incrementNet($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        $this->recalculateGameTime($gameId);

        return EquipmentResult::Added;
    }

    protected function incrementVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        if (!$this->gameUserRepository->incrementVolleyball($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        return EquipmentResult::Added;
    }

    private function ensureGameUser(int $gameId, int $telegramUserId): void
    {
        if (null === $this->gameUserRepository->findByGameUser($gameId, $telegramUserId)) {
            $this->gameUserRepository->create($gameId, $telegramUserId, $this->resolveGameTime($gameId));
        }
    }

    private function ensureGameUserSlot(int $gameId, int $telegramUserId): void
    {
        if (empty($this->gameSlotRepository->findPositionsByUser($gameId, $telegramUserId))) {
            $this->addSlot($gameId, $telegramUserId);
        }
    }

    private function ensureUserInGame(
        int $gameId,
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
    ): void {
        $this->userRepository->upsert($telegramUserId, $firstName, $lastName, $username);
        $this->ensureGameUser($gameId, $telegramUserId);
        $this->ensureGameUserSlot($gameId, $telegramUserId);
    }

    private function addSlot(int $gameId, int $telegramUserId): void
    {
        $this->gameSlotRepository->create(
            $gameId,
            $telegramUserId,
            $this->gameSlotRepository->getNextPosition($gameId),
        );
    }

    private function resolveGameTime(int $gameId): ?string
    {
        $title = $this->gameRepository->findTitleByGameId($gameId);

        if (null === $title) {
            return null;
        }

        return TimeExtractor::extract($title);
    }

    private function recalculateGameTime(int $gameId): void
    {
        $earliestTime = $this->gameUserRepository->findEarliestTimeWithNet($gameId)
            ?? $this->gameUserRepository->findEarliestTime($gameId);

        if (null === $earliestTime) {
            return;
        }

        $game = $this->gameRepository->findById($gameId);

        if (null === $game) {
            return;
        }

        $title = (string) $game['title'];
        $currentTime = TimeExtractor::extractRaw($title);

        if (null === $currentTime || $currentTime === $earliestTime) {
            return;
        }

        $updatedTitle = str_replace($currentTime, $earliestTime, $title);
        $createdAt = new DateTimeImmutable((string) $game['created_at']);

        $this->gameRepository->updateTitle($gameId, $updatedTitle, ParsedTitle::parse($updatedTitle, $createdAt));
    }

    private function parseTitle(int $gameId, string $title): ParsedTitle
    {
        $createdAt = $this->gameRepository->findCreatedAtByGameId($gameId) ?? new DateTimeImmutable();

        return ParsedTitle::parse($title, $createdAt);
    }
}
