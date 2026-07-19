<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UserSettingsMessageBuilder extends AbstractAdminMessageBuilder
{
    private const string REMOVE_SLOT       = 'Remove Slot';
    private const string REMOVE_VOLLEYBALL = '-🏐';
    private const string ADD_VOLLEYBALL    = '+🏐';
    private const string REMOVE_NET        = '-🕸️';
    private const string ADD_NET           = '+🕸️';

    public function buildUserSettings(
        int $gameId,
        int $telegramUserId,
        ?array $userRow,
        int $slotCount,
        int $volleyball,
        int $net,
    ): TelegramMessage {
        return $this->buildMessage(
            $this->buildUserSettingsText($gameId, $telegramUserId, $userRow, $slotCount, $volleyball, $net),
            $this->buildUserSettingsKeyboard($gameId, $telegramUserId, $slotCount),
        );
    }

    private function buildUserSettingsText(
        int $gameId,
        int $telegramUserId,
        ?array $userRow,
        int $slotCount,
        int $volleyball,
        int $net,
    ): string {
        return implode($this->formatter->newLine(), [
            $this->formatHeader("User Settings #$gameId"),
            $this->buildNamePart($telegramUserId, $userRow),
            $this->formatter->escape("Telegram ID: $telegramUserId"),
            $this->formatter->escape("Slots: $slotCount"),
            $this->formatter->escape("Volleyball: $volleyball"),
            $this->formatter->escape("Net: $net"),
        ]);
    }

    private function buildNamePart(int $telegramUserId, ?array $userRow): string
    {
        if (null === $userRow) {
            return $this->formatter->escape("User $telegramUserId");
        }

        $userName = User::buildName($userRow['first_name'], $userRow['last_name'] ?? null);

        $userLink = User::buildLink($userRow['username'] ?? null);
        if (null !== $userLink) {
            return $this->formatter->link($userName, $userLink);
        }

        return $this->formatter->escape($userName);
    }

    private function buildUserSettingsKeyboard(int $gameId, int $telegramUserId, int $slotCount): array
    {
        $keyboard = [];

        if (0 < $slotCount) {
            $keyboard[] = $this->buildRemoveSlotRow($gameId, $telegramUserId);
        }

        $keyboard[] = $this->buildVolleyballRow($gameId, $telegramUserId);
        $keyboard[] = $this->buildNetRow($gameId, $telegramUserId);
        $keyboard[] = $this->usersListBackRow($gameId);

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
    private function usersListBackRow(int $gameId): array
    {
        return $this->backButtonRow(
            AdminCallbackData::create(AdminCallbackAction::GameUsers)
                ->withGameId($gameId)
                ->withPage(1)
        );
    }

    public function buildUserNotFound(int $gameId): TelegramMessage
    {
        $text = $this->formatHeader("User Settings #$gameId")
            . $this->formatter->newLine() . $this->formatter->escape('User not found in this game');

        return $this->buildMessage($text, [$this->usersListBackRow($gameId)]);
    }
}
