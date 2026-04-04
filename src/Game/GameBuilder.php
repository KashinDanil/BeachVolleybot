<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

readonly class GameBuilder
{
    private const string PROFILE_URL_PREFIX = 'https://t.me/';

    /**
     * @param array<string, mixed> $gameRow
     * @param list<array<string, mixed>> $slotRows
     * @param list<array<string, mixed>> $gamePlayerRows
     * @param list<array<string, mixed>> $playerRows
     */
    public function __construct(
        private array $gameRow,
        private array $slotRows,
        private array $gamePlayerRows,
        private array $playerRows,
    ) {
    }

    public function build(): GameInterface
    {
        return new Game(
            gameId: (int)$this->gameRow['game_id'],
            inlineQueryId: (string)$this->gameRow['inline_query_id'],
            inlineMessageId: (string)$this->gameRow['inline_message_id'],
            title: (string)$this->gameRow['title'],
            players: $this->buildPlayers(),
        );
    }

    /** @return PlayerInterface[] */
    private function buildPlayers(): array
    {
        $gamePlayersIndex = array_column($this->gamePlayerRows, null, 'telegram_user_id');
        $playersIndex = array_column($this->playerRows, null, 'telegram_user_id');

        $players = [];

        foreach ($this->slotRows as $slot) {
            $telegramUserId = $slot['telegram_user_id'];
            $players[] = $this->buildPlayer($slot, $gamePlayersIndex[$telegramUserId], $playersIndex[$telegramUserId]);
        }

        return $players;
    }

    private function buildPlayer(array $slot, array $gamePlayerRow, array $playerRow): Player
    {
        return new Player(
            number: (string)$slot['position'],
            name: $this->buildName($playerRow),
            link: $this->buildLink($playerRow),
            ball: (int)$gamePlayerRow['ball'],
            net: (int)$gamePlayerRow['net'],
            time: $gamePlayerRow['time'],
        );
    }

    private function buildName(array $playerRow): string
    {
        return trim($playerRow['first_name'] . ' ' . ($playerRow['last_name'] ?? ''));
    }

    private function buildLink(array $playerRow): ?string
    {
        if (null === $playerRow['username']) {
            return null;
        }

        return self::PROFILE_URL_PREFIX . $playerRow['username'];
    }
}
