<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\User\NotificationType;

final readonly class NotificationTypeTexts
{
    private function __construct(
        public string $label,
        public string $trigger,
        public string $descriptionFormat,
        public ?string $hint = null,
    ) {
    }

    public static function forType(NotificationType $type): self
    {
        return match ($type) {
            NotificationType::GameReachedMinimumPlayers => new self(
                label: '✅ Game is on',
                trigger: "a game you've joined gets enough players to take place",
                descriptionFormat: 'Enough players have joined, so the game %s at %s will take place:',
            ),
            NotificationType::GameShortBeforeKickoff => new self(
                label: '⚠️ Short of players',
                trigger: "kickoff is near and a game you've joined still doesn't have enough players",
                descriptionFormat: "The game %s at %s starts soon, but there still aren't enough players:",
            ),
            NotificationType::PromotedIntoGame => new self(
                label: "⬆️ You're in",
                trigger: 'a spot opens up and you move from the reserve to playing',
                descriptionFormat: "A spot opened up, and you're now playing %s at %s:",
            ),
            NotificationType::BumpedFromGame => new self(
                label: "⬇️ You're out",
                trigger: 'you move from playing to the reserve, for example after a net or volleyball is removed',
                descriptionFormat: "You've moved to the reserve and are no longer playing %s at %s:",
            ),
            NotificationType::KickoffTimeChanged => new self(
                label: '🕒 Game time changed',
                trigger: "the day or time of a game you've joined changes",
                descriptionFormat: 'The game time has changed, and it now takes place %s at %s:',
                hint: "To change your own time in the game, reply to the game message in the chat with the time you'll arrive, like 19:30.",
            ),
        };
    }
}
