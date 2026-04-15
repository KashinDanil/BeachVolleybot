<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class GameDetailMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string GAME_NOT_FOUND = 'Game not found';

    public function buildGameNotFound(): TelegramMessage
    {
        $text = $this->formatHeader('Game') . $this->formatter->newLine() . $this->formatter->escape(self::GAME_NOT_FOUND);

        return $this->buildMessage($text, [
            $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(1)),
        ]);
    }

    public function buildGameDetail(GameInterface $game): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildGameDetailText($game),
            $this->buildGameDetailKeyboard($game),
        );
    }

    private function buildGameDetailText(GameInterface $game): string
    {
        $lines = [
            $this->formatHeader("Game #{$game->getGameId()}"),
            $this->formatter->escape($game->getTitle()),
        ];

        if (null !== $game->getLocation()) {
            $lines[] = $this->formatter->escape("Location: {$game->getLocation()}");
        }

        $players = $game->getPlayers();
        $playerCount = array_map(static fn($player) => $player->getTelegramUserId(), $players)
                |> array_unique(...)
                |> count(...);

        $lines[] = $this->formatter->escape("Players: $playerCount");
        $lines[] = $this->formatter->escape("Slots: " . count($players));

        return implode($this->formatter->newLine(), $lines);
    }

    private function buildGameDetailKeyboard(GameInterface $game): array
    {
        $gameId = $game->getGameId();

        $keyboard = [
            [
                $this->buildActionButton(
                    'Players',
                    AdminCallbackData::create(AdminCallbackAction::GamePlayers)
                        ->withGameId($gameId)
                        ->withPage(1),
                ),
            ],
        ];

        if (null !== $game->getLocation()) {
            $keyboard[] = [
                $this->buildActionButton(
                    'Remove Location',
                    AdminCallbackData::create(AdminCallbackAction::RemoveLocation)->withGameId($gameId),
                ),
            ];
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(1));

        return $keyboard;
    }
}
