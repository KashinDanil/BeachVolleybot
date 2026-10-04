<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Notifications\KickoffChangeNotifier;
use BeachVolleybot\Notifications\LineupChangeNotifier;
use BeachVolleybot\Notifications\MinimumPlayersNotifier;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\User\UserManager;
use DateTimeImmutable;
use RuntimeException;

readonly class GameManager
{
    protected GameRepository $gameRepository;

    protected GameUserManager $gameUserManager;

    protected GameSlotManager $gameSlotManager;

    protected UserManager $userManager;

    public function __construct(
        protected MinimumPlayersNotifier $minimumPlayersNotifier = new MinimumPlayersNotifier(),
        protected LineupChangeNotifier $lineupChangeNotifier = new LineupChangeNotifier(),
        protected KickoffChangeNotifier $kickoffChangeNotifier = new KickoffChangeNotifier(),
    ) {
        $this->gameRepository = new GameRepository(Connection::get());
        $this->gameUserManager = new GameUserManager();
        $this->gameSlotManager = new GameSlotManager();
        $this->userManager = new UserManager();
    }

    public function createGame(NewGameData $data): int
    {
        $this->userManager->ensureUserRecord($data->creator);

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

        $this->gameUserManager->createGameUser(
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
        $this->userManager->ensureUserRecord($user);
        $this->ensureGameUser($gameId, $user->id);
        $this->addSlot($gameId, $user->id);
    }

    public function leaveGame(int $gameId, int $telegramUserId): LeaveResult
    {
        $positions = $this->gameSlotManager->findPositionsByUser($gameId, $telegramUserId);

        if (empty($positions)) {
            return LeaveResult::NotJoined;
        }

        $game = $this->getGameRecord($gameId);
        $lineupBefore = $this->lineupChangeNotifier->capture($game);

        $this->gameSlotManager->deleteSlot($gameId, max($positions));

        if (1 === count($positions)) {
            $this->gameUserManager->deleteGameUser($gameId, $telegramUserId);
        }

        $this->recalculateGameTime($game, $telegramUserId);
        $this->lineupChangeNotifier->notifyChanges($lineupBefore, $telegramUserId);

        return LeaveResult::Left;
    }

    public function addNet(int $gameId, TelegramUser $user): EquipmentResult
    {
        $this->ensureUserInGame($gameId, $user);

        return $this->incrementNet($gameId, $user->id);
    }

    public function removeNet(int $gameId, int $telegramUserId): EquipmentResult
    {
        $gameUser = $this->gameUserManager->findGameUserRecord($gameId, $telegramUserId);

        if (null === $gameUser) {
            return EquipmentResult::NotJoined;
        }

        if (0 === $gameUser->net) {
            return EquipmentResult::NoneLeft;
        }

        $game = $this->getGameRecord($gameId);
        $lineupBefore = $this->lineupChangeNotifier->capture($game);

        if (!$this->gameUserManager->decrementNet($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        $this->recalculateGameTime($game, $telegramUserId);
        $this->lineupChangeNotifier->notifyChanges($lineupBefore, $telegramUserId);

        return EquipmentResult::Removed;
    }

    public function addVolleyball(int $gameId, TelegramUser $user): EquipmentResult
    {
        $this->ensureUserInGame($gameId, $user);

        return $this->incrementVolleyball($gameId, $user->id);
    }

    public function removeVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        $gameUser = $this->gameUserManager->findGameUserRecord($gameId, $telegramUserId);

        if (null === $gameUser) {
            return EquipmentResult::NotJoined;
        }

        if (0 === $gameUser->volleyball) {
            return EquipmentResult::NoneLeft;
        }

        $game = $this->getGameRecord($gameId);
        $lineupBefore = $this->lineupChangeNotifier->capture($game);

        if (!$this->gameUserManager->decrementVolleyball($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        $this->recalculateGameTime($game, $telegramUserId);
        $this->lineupChangeNotifier->notifyChanges($lineupBefore, $telegramUserId);

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

    public function setUserTime(int $gameId, TelegramUser $user, string $time): void
    {
        $this->ensureUserInGame($gameId, $user);

        $this->gameUserManager->updateTime($gameId, $user->id, $time);

        $this->recalculateGameTime($this->getGameRecord($gameId), $user->id);
    }

    public function changeTitle(GameRecord $game, TelegramUser $user, string $newTitle): void
    {
        $normalizedTitle = TimeExtractor::normalize($newTitle);
        $proposedTime = TimeExtractor::extract($normalizedTitle);
        if (null === $proposedTime) {
            return;
        }

        $this->ensureUserInGame($game->gameId, $user);
        $this->gameUserManager->updateTime($game->gameId, $user->id, $proposedTime);

        $this->updateTitle($game, $this->titleWithEarliestTime($game->gameId, $normalizedTitle), $user->id);
    }

    public function resolveGameIdByGameKey(string $gameKey): ?int
    {
        return $this->gameRepository->findGameIdByGameKey($gameKey);
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

    /** Only for a game the caller knows exists, e.g. one the user already has a row in. */
    private function getGameRecord(int $gameId): GameRecord
    {
        return $this->findGameRecordById($gameId) ?? throw new RuntimeException("Game not found: $gameId");
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
        $game = $this->getGameRecord($gameId);
        $lineupBefore = $this->lineupChangeNotifier->capture($game);

        if (!$this->gameUserManager->incrementNet($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        $this->recalculateGameTime($game, $telegramUserId);
        $this->lineupChangeNotifier->notifyChanges($lineupBefore, $telegramUserId);

        return EquipmentResult::Added;
    }

    protected function incrementVolleyball(int $gameId, int $telegramUserId): EquipmentResult
    {
        $game = $this->getGameRecord($gameId);
        $lineupBefore = $this->lineupChangeNotifier->capture($game);

        if (!$this->gameUserManager->incrementVolleyball($gameId, $telegramUserId)) {
            return EquipmentResult::Error;
        }

        $this->recalculateGameTime($game, $telegramUserId);
        $this->lineupChangeNotifier->notifyChanges($lineupBefore, $telegramUserId);

        return EquipmentResult::Added;
    }

    private function ensureGameUser(int $gameId, int $telegramUserId): void
    {
        if (null === $this->gameUserManager->findGameUserRecord($gameId, $telegramUserId)) {
            $this->gameUserManager->createGameUser($gameId, $telegramUserId, $this->resolveGameTime($gameId));
        }
    }

    private function ensureGameUserSlot(int $gameId, int $telegramUserId): void
    {
        if (empty($this->gameSlotManager->findPositionsByUser($gameId, $telegramUserId))) {
            $this->addSlot($gameId, $telegramUserId);
        }
    }

    private function ensureUserInGame(int $gameId, TelegramUser $user): void
    {
        $this->userManager->ensureUserRecord($user);
        $this->ensureGameUser($gameId, $user->id);
        $this->ensureGameUserSlot($gameId, $user->id);
    }

    private function addSlot(int $gameId, int $telegramUserId): void
    {
        $this->gameSlotManager->addSlot($gameId, $telegramUserId);

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

    private function recalculateGameTime(GameRecord $game, int $actorId): void
    {
        $updatedTitle = $this->titleWithEarliestTime($game->gameId, $game->title);

        if ($updatedTitle === $game->title) {
            return;
        }

        $this->updateTitle($game, $updatedTitle, $actorId);
    }

    private function titleWithEarliestTime(int $gameId, string $title): string
    {
        $earliestGameTime = $this->gameUserManager->findEarliestTime($gameId);
        $currentGameTime = TimeExtractor::extractRaw($title);

        if (null === $earliestGameTime || null === $currentGameTime) {
            return $title;
        }

        return str_replace($currentGameTime, $earliestGameTime, $title);
    }

    private function updateTitle(GameRecord $game, string $title, int $actorId): void
    {
        $parsedTitle = ParsedTitle::parse($title, $game->createdAt);
        $settings = $game->settings->withPlayersPerNet($parsedTitle->playersPerNet);

        $this->gameRepository->updateTitleWithDependencies(
            $game->gameId,
            $title,
            $parsedTitle->kickoffAt,
            $parsedTitle->venueName,
            $settings,
        );

        $this->kickoffChangeNotifier->notifyIfChanged($game, $parsedTitle->kickoffAt, $actorId);
        $this->lineupChangeNotifier->notifyPlayersPerNetChange($game, $settings, $actorId);
    }
}
