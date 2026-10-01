<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Notifications\MinimumPlayersNotifier;
use BeachVolleybot\Telegram\Messages\GameMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;
use BeachVolleybot\Validator\Validator;
use DateTimeImmutable;
use InvalidArgumentException;

readonly class GameManager
{
    protected GameRepository $gameRepository;

    protected GameMessageRepository $gameMessageRepository;

    protected GameUserRepository $gameUserRepository;

    protected GameSlotRepository $gameSlotRepository;

    protected UserManager $userManager;

    public function __construct(
        protected MinimumPlayersNotifier $minimumPlayersNotifier = new MinimumPlayersNotifier(),
    ) {
        $db = Connection::get();
        $this->gameRepository = new GameRepository($db);
        $this->gameMessageRepository = new GameMessageRepository($db);
        $this->gameUserRepository = new GameUserRepository($db);
        $this->gameSlotRepository = new GameSlotRepository($db);
        $this->userManager = new UserManager();
    }

    public function createGame(NewGameData $data): int
    {
        $this->userManager->upsertUser($data->creator);

        $parsedTitle = ParsedTitle::parse($data->title, $data->createdAt);
        $settings = new GameSettings($parsedTitle->playersPerNet);

        $gameId = $this->gameRepository->create(
            $data->title,
            $data->creator->id,
            $data->gameKey,
            $parsedTitle->kickoffAt,
            $parsedTitle->venueName,
            settings: $settings,
        );

        $this->gameUserRepository->create(
            $gameId,
            $data->creator->id,
            TimeExtractor::extract($data->title),
            NewGameData::INITIAL_VOLLEYBALL,
            NewGameData::INITIAL_NET,
        );

        $this->addSlot($gameId, $data->creator->id);

        return $gameId;
    }

    public function joinGame(int $gameId, TelegramUser $user): void
    {
        $this->userManager->upsertUser($user);
        $this->ensureGameUser($gameId, $user->id);
        $this->addSlot($gameId, $user->id);
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

    public function addNet(int $gameId, TelegramUser $user): EquipmentResult
    {
        $this->ensureUserInGame($gameId, $user);

        return $this->incrementNet($gameId, $user->id);
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

    public function addVolleyball(int $gameId, TelegramUser $user): EquipmentResult
    {
        $this->ensureUserInGame($gameId, $user);

        return $this->incrementVolleyball($gameId, $user->id);
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

    public function setPlayersPerNet(int $gameId, ?int $playersPerNet): void
    {
        $validationState = new Validator([
            new MinimumPlayersPerNetRule($playersPerNet),
        ])->validate();

        if (!$validationState->isSuccess()) {
            throw new InvalidArgumentException($validationState->getError()->getMessage());
        }

        $stored = $this->findGameRecordById($gameId)?->settings ?? new GameSettings();

        $this->gameRepository->updateSettings($gameId, $stored->withPlayersPerNet($playersPerNet));
    }

    public function setUserTime(int $gameId, TelegramUser $user, string $time): void
    {
        $this->ensureUserInGame($gameId, $user);

        $this->gameUserRepository->updateTime($gameId, $user->id, $time);

        $this->recalculateGameTime($gameId);
    }

    public function changeTitle(GameRecord $game, TelegramUser $user, string $newTitle): void
    {
        $normalizedTitle = TimeExtractor::normalize($newTitle);
        $proposedTime = TimeExtractor::extract($normalizedTitle);
        if (null === $proposedTime) {
            return;
        }

        $parsedTitle = ParsedTitle::parse($normalizedTitle, $game->createdAt);

        $this->gameRepository->updateTitleWithDependencies(
            $game->gameId,
            $normalizedTitle,
            $parsedTitle->kickoffAt,
            $parsedTitle->venueName,
            $game->settings->withPlayersPerNet($parsedTitle->playersPerNet),
        );
        $this->setUserTime($game->gameId, $user, $proposedTime);
    }

    public function isUserInGame(int $gameId, int $telegramUserId): bool
    {
        return $this->gameUserRepository->exists($gameId, $telegramUserId);
    }

    public function addInlineMessage(int $gameId, string $inlineMessageId, string $inlineQueryId): void
    {
        $this->gameMessageRepository->addInlineMessage($gameId, $inlineMessageId, $inlineQueryId);
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

    public function resolveGameIdByGameMessage(GameMessage $gameMessage): ?int
    {
        if ($gameMessage->isInline()) {
            return $this->resolveGameIdByInlineMessageId($gameMessage->inlineMessageId);
        }

        return $this->resolveGameIdByChatMessage($gameMessage->chatId, $gameMessage->messageId);
    }

    public function findGameRecordByGameKey(string $gameKey): ?GameRecord
    {
        return $this->buildGameRecord($this->gameRepository->findByGameKey($gameKey));
    }

    public function findGameRecordById(int $gameId): ?GameRecord
    {
        return $this->buildGameRecord($this->gameRepository->findById($gameId));
    }

    /** @return list<GameRecord> */
    public function findGameRecordsPage(int $limit, int $offset): array
    {
        return $this->toGameRecords($this->gameRepository->findAllDescending($limit, $offset));
    }

    public function countGames(): int
    {
        return $this->gameRepository->countAll();
    }

    /** @return list<GameRecord> */
    public function findGameRecordsPageByCreator(int $createdBy, int $limit, int $offset): array
    {
        return $this->toGameRecords($this->gameRepository->findByCreator($createdBy, $limit, $offset));
    }

    public function countGamesByCreator(int $createdBy): int
    {
        return $this->gameRepository->countByCreator($createdBy);
    }

    /** @return list<GameRecord> */
    public function findUpcomingGameRecords(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->toGameRecords($this->gameRepository->findUpcoming($from, $to));
    }

    /** @return list<GameRecord> */
    public function findGameRecordsByKickoffBetween(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return $this->toGameRecords($this->gameRepository->findByKickoffBetween($from, $until));
    }

    private function buildGameRecord(?array $row): ?GameRecord
    {
        return null !== $row ? GameRecord::fromRow($row) : null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<GameRecord>
     */
    private function toGameRecords(array $rows): array
    {
        return array_map(GameRecord::fromRow(...), $rows);
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

    private function ensureUserInGame(int $gameId, TelegramUser $user): void
    {
        $this->userManager->upsertUser($user);
        $this->ensureGameUser($gameId, $user->id);
        $this->ensureGameUserSlot($gameId, $user->id);
    }

    private function addSlot(int $gameId, int $telegramUserId): void
    {
        $this->gameSlotRepository->create(
            $gameId,
            $telegramUserId,
            $this->gameSlotRepository->getNextPosition($gameId),
        );

        $this->minimumPlayersNotifier->notifyIfReached($gameId, $telegramUserId);
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

        $gameRecord = $this->findGameRecordById($gameId);

        if (null === $gameRecord) {
            return;
        }

        $currentTime = TimeExtractor::extractRaw($gameRecord->title);

        if (null === $currentTime || $currentTime === $earliestTime) {
            return;
        }

        $updatedTitle = str_replace($currentTime, $earliestTime, $gameRecord->title);
        $parsedTitle = ParsedTitle::parse($updatedTitle, $gameRecord->createdAt);

        $this->gameRepository->updateTitleWithDependencies(
            $gameId,
            $updatedTitle,
            $parsedTitle->kickoffAt,
            $parsedTitle->venueName,
            $gameRecord->settings,
        );
    }
}
