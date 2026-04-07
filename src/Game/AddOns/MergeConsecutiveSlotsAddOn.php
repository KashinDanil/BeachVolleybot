<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\AddOns;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MessageBuilders\GroupSizeTelegramMessageBuilder;

/**
 * Merges consecutive slots belonging to the same player into a single entry.
 *
 * Before: 1. Alice, 2. Alice, 3. Bob
 * After: 1-2. Alice, 3. Bob
 */
final class MergeConsecutiveSlotsAddOn implements GameAddOnInterface
{
    public function transform(GameInterface $game): GameInterface
    {
        return new Game(
            gameId: $game->getGameId(),
            inlineQueryId: $game->getInlineQueryId(),
            inlineMessageId: $game->getInlineMessageId(),
            title: $game->getTitle(),
            players: $this->mergeConsecutive($game->getPlayers()),
            location: $game->getLocation(),
            telegramMessageBuilder: new GroupSizeTelegramMessageBuilder(),
        );
    }

    /**
     * @param PlayerInterface[] $players
     *
     * @return PlayerInterface[]
     */
    private function mergeConsecutive(array $players): array
    {
        $groups = $this->groupConsecutive($players);

        return array_map($this->mergeGroup(...), $groups);
    }

    /**
     * @param PlayerInterface[] $players
     *
     * @return list<PlayerInterface[]>
     */
    private function groupConsecutive(array $players): array
    {
        $groups = [];
        $previousUserId = null;

        foreach ($players as $player) {
            if ($player->getTelegramUserId() === $previousUserId) {
                $groups[array_key_last($groups)][] = $player;
            } else {
                $groups[] = [$player];
                $previousUserId = $player->getTelegramUserId();
            }
        }

        return $groups;
    }

    /** @param PlayerInterface[] $group */
    private function mergeGroup(array $group): Player
    {
        $first = $group[0];
        $last = $group[array_key_last($group)];

        return new Player(
            telegramUserId: $first->getTelegramUserId(),
            number: $this->buildNumber($first, $last),
            name: $first->getName(),
            link: $first->getLink(),
            volleyball: $first->getVolleyball(),
            net: $first->getNet(),
            time: $first->getTime(),
        );
    }

    private function buildNumber(PlayerInterface $first, PlayerInterface $last): string
    {
        if ($first === $last) {
            return $first->getNumber();
        }

        return $first->getNumber() . '-' . $last->getNumber();
    }
}