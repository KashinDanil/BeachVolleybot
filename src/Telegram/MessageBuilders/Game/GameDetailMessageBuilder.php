<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Game;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Admin\AbstractAdminMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\ProfileNameFormatter;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserRecord;

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

    public function buildGameDetail(
        GameInterface $game,
        ?UserRecord $creator,
        Role $viewerRole,
        bool $sharingEnabled = true,
    ): TelegramMessage {
        return $this->buildMessage(
            $this->buildGameDetailText($game, $creator, $sharingEnabled),
            $this->buildGameDetailKeyboard($game, $viewerRole, $sharingEnabled),
        );
    }

    private function buildGameDetailText(GameInterface $game, ?UserRecord $creator, bool $sharingEnabled): string
    {
        $lines = [$this->formatHeader("Game #{$game->getGameId()}")];

        if (!$sharingEnabled) {
            // Empty line breaks the notice's blockquote from the title's blockquote below,
            // which Telegram would otherwise merge into a single quote block.
            $lines[] = ShareGameMessageBuilder::renderDisabledNotice($this->formatter);
            $lines[] = '';
        }

        $lines[] = $this->formatter->blockquote($this->formatter->escape($game->getTitle()));

        $creatorLine = $this->buildCreatorLine($creator);

        if (null !== $creatorLine) {
            $lines[] = $creatorLine;
        }

        if (null !== $game->getLocation()) {
            $lines[] = $this->formatter->escape("Location: {$game->getLocation()}");
        }

        $players = $game->getPlayers();
        $userCount = array_map(static fn($player) => $player->getTelegramUserId(), $players)
                |> array_unique(...)
                |> count(...);

        $lines[] = $this->formatter->escape("Users: $userCount");
        $lines[] = $this->formatter->escape("Slots: " . count($players));

        return implode($this->formatter->newLine(), $lines);
    }

    private function buildCreatorLine(?UserRecord $creator): ?string
    {
        if (null === $creator) {
            return null;
        }

        return $this->formatter->escape('Creator: ') . new ProfileNameFormatter($this->formatter)->formatUser($creator);
    }

    private function buildGameDetailKeyboard(GameInterface $game, Role $viewerRole, bool $sharingEnabled): array
    {
        $gameId = $game->getGameId();

        $keyboard = [];

        if ($sharingEnabled) {
            $keyboard[] = [
                $this->buildSwitchInlineQueryButton(
                    ShareGameMessageBuilder::BUTTON_TEXT,
                    ShareGameMessageBuilder::switchQuery($gameId),
                ),
            ];
        }

        $usersAction = AdminCallbackAction::GameUsers;
        if ($viewerRole->isAtLeast($usersAction->requiredRole())) {
            $keyboard[] = [
                $this->buildActionButton(
                    'Users',
                    AdminCallbackData::create($usersAction)
                        ->withGameId($gameId)
                        ->withPage(1),
                ),
            ];
        }

        $removeLocationAction = AdminCallbackAction::RemoveLocation;
        if (null !== $game->getLocation() && $viewerRole->isAtLeast($removeLocationAction->requiredRole())) {
            $keyboard[] = [
                $this->buildActionButton(
                    'Remove Location',
                    AdminCallbackData::create($removeLocationAction)->withGameId($gameId),
                ),
            ];
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::GamesList)->withPage(1));

        return $keyboard;
    }
}
