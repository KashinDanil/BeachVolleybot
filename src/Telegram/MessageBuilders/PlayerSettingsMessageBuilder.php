<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class PlayerSettingsMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string REMOVE_SLOT       = 'Remove Slot';
    private const string REMOVE_VOLLEYBALL = '-🏐';
    private const string ADD_VOLLEYBALL    = '+🏐';
    private const string REMOVE_NET        = '-🕸️';
    private const string ADD_NET           = '+🕸️';

    public function buildPlayerSettings(
        int $gameId,
        int $telegramUserId,
        string $playerName,
        int $slotCount,
        int $volleyball,
        int $net,
    ): TelegramMessage {
        return $this->buildMessage(
            $this->buildPlayerSettingsText($gameId, $playerName, $slotCount, $volleyball, $net),
            $this->buildPlayerSettingsKeyboard($gameId, $telegramUserId, $slotCount),
        );
    }

    private function buildPlayerSettingsText(
        int $gameId,
        string $playerName,
        int $slotCount,
        int $volleyball,
        int $net,
    ): string {
        return implode($this->formatter->newLine(), [
            $this->formatHeader("Player Settings #$gameId"),
            $this->formatter->escape($playerName),
            $this->formatter->escape("Slots: $slotCount"),
            $this->formatter->escape("Volleyball: $volleyball"),
            $this->formatter->escape("Net: $net"),
        ]);
    }

    private function buildPlayerSettingsKeyboard(int $gameId, int $telegramUserId, int $slotCount): array
    {
        $keyboard = [];

        if (0 < $slotCount) {
            $keyboard[] = $this->buildRemoveSlotRow($gameId, $telegramUserId);
        }

        $keyboard[] = $this->buildVolleyballRow($gameId, $telegramUserId);
        $keyboard[] = $this->buildNetRow($gameId, $telegramUserId);
        $keyboard[] = $this->playersListBackRow($gameId);

        return $keyboard;
    }

    /** @return list<array{text: string, callback_data: string}> */
    private function buildRemoveSlotRow(int $gameId, int $telegramUserId): array
    {
        return [
            $this->buildActionButton(
                self::REMOVE_SLOT,
                AdminCallbackData::create(AdminCallbackAction::RemoveSlot)
                    ->withGameId($gameId)
                    ->withUserId($telegramUserId),
            ),
        ];
    }

    /** @return list<array{text: string, callback_data: string}> */
    private function buildVolleyballRow(int $gameId, int $telegramUserId): array
    {
        return [
            $this->buildActionButton(
                self::REMOVE_VOLLEYBALL,
                AdminCallbackData::create(AdminCallbackAction::RemoveVolleyball)
                    ->withGameId($gameId)
                    ->withUserId($telegramUserId),
            ),
            $this->buildActionButton(
                self::ADD_VOLLEYBALL,
                AdminCallbackData::create(AdminCallbackAction::AddVolleyball)
                    ->withGameId($gameId)
                    ->withUserId($telegramUserId),
            ),
        ];
    }

    /** @return list<array{text: string, callback_data: string}> */
    private function buildNetRow(int $gameId, int $telegramUserId): array
    {
        return [
            $this->buildActionButton(
                self::REMOVE_NET,
                AdminCallbackData::create(AdminCallbackAction::RemoveNet)
                    ->withGameId($gameId)
                    ->withUserId($telegramUserId),
            ),
            $this->buildActionButton(
                self::ADD_NET,
                AdminCallbackData::create(AdminCallbackAction::AddNet)
                    ->withGameId($gameId)
                    ->withUserId($telegramUserId),
            ),
        ];
    }

    /** @return list<array{text: string, callback_data: string}> */
    private function playersListBackRow(int $gameId): array
    {
        return $this->backButtonRow(
            AdminCallbackData::create(AdminCallbackAction::GamePlayers)
                ->withGameId($gameId)
                ->withPage(1)
        );
    }

    public function buildPlayerNotFound(int $gameId): TelegramMessage
    {
        $text = $this->formatHeader("Player Settings #$gameId")
            . $this->formatter->newLine() . $this->formatter->escape('Player not found in this game');

        return $this->buildMessage($text, [$this->playersListBackRow($gameId)]);
    }
}
