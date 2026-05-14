<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\GameCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\ChangeTitleHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\JoinWithTimeHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\SetLiveLocationHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\SetLocationHandler;
use BeachVolleybot\Processors\Handlers\PinHandlers\DeletePinNotificationHandler;
use BeachVolleybot\Processors\Handlers\PinHandlers\PinMessageHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\AdminCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\SendShareButtonHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\SettingsMenuCommandHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserCallbackQueryHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserGamesListCommandHandler;
use BeachVolleybot\Processors\Handlers\PrivateHandlers\UserStartCommandHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use ReflectionClass;

final class HandlerExclusivityTest extends ProcessorTestCase
{
    public function testNoUpdateMatchesMoreThanOneHandler(): void
    {
        $handlers = $this->allHandlers();
        $conflicts = [];

        foreach ($this->fixtures() as $label => $update) {
            $matched = $this->findMatchingHandlerNames($handlers, $update);

            if (count($matched) > 1) {
                $conflicts[$label] = $matched;
            }
        }

        $this->assertEmpty(
            $conflicts,
            "Handlers must have mutually exclusive match patterns. Overlaps:\n" . $this->formatConflicts($conflicts),
        );
    }

    /**
     * @return AbstractProcessorHandler[]
     */
    private function allHandlers(): array
    {
        return [
            new GameCallbackQueryHandler(),
            new SetLiveLocationHandler(),
            new SetLocationHandler(),
            new JoinWithTimeHandler(),
            new ChangeTitleHandler(),
            new DeletePinNotificationHandler(),
            new PinMessageHandler(),
            new AdminCallbackQueryHandler(),
            new UserCallbackQueryHandler(),
            new SendShareButtonHandler(),
            new SettingsMenuCommandHandler(),
            new UserGamesListCommandHandler(),
            new UserStartCommandHandler(),
        ];
    }

    /**
     * @return array<string, TelegramUpdate>
     */
    private function fixtures(): array
    {
        $adminId = ADMINS_TELEGRAM_USER_IDS[0] ?? 12345678;
        $nonAdminId = 999;

        return [
            'inline callback (join)' => TelegramUpdate::fromArray(
                $this->callbackQueryPayload('iqi1', json_encode(['a' => 'j', 'q' => 'iq1'])),
            ),
            'non-inline admin button by admin' => TelegramUpdate::fromArray(
                $this->adminCallbackQueryPayload(json_encode(['aa' => 'st']), fromId: $adminId),
            ),
            'non-inline user button by admin' => TelegramUpdate::fromArray(
                $this->adminCallbackQueryPayload(json_encode(['ua' => 'ugl']), fromId: $adminId),
            ),
            'non-inline user button by non-admin' => TelegramUpdate::fromArray(
                $this->adminCallbackQueryPayload(json_encode(['ua' => 'ugl']), fromId: $nonAdminId),
            ),
            'edited reply with location' => TelegramUpdate::fromArray(
                $this->editedLocationMessagePayload(latitude: 41.4, longitude: 2.2, inlineQueryId: 'iq1'),
            ),
            'reply with location (game queue)' => TelegramUpdate::fromArray(
                $this->locationMessagePayload(latitude: 41.4, longitude: 2.2, inlineQueryId: 'iq1'),
            ),
            'reply with time-only text' => TelegramUpdate::fromArray(
                $this->replyMessagePayload('18:00', 'iq1'),
            ),
            'reply with non-time text' => TelegramUpdate::fromArray(
                $this->replyMessagePayload('hello', 'iq1'),
            ),
            'group pin notification from bot' => TelegramUpdate::fromArray(
                $this->pinNotificationPayload(chatId: -100, messageId: 11, pinnedMessageId: 10),
            ),
            'group via-bot message with keyboard' => TelegramUpdate::fromArray(
                $this->viaBotKeyboardMessagePayload(),
            ),
            'private /games' => TelegramUpdate::fromArray(
                $this->privateMessagePayload('/games', fromId: $nonAdminId),
            ),
            'private /start' => TelegramUpdate::fromArray(
                $this->privateMessagePayload('/start', fromId: $nonAdminId),
            ),
            'private /settings by admin' => TelegramUpdate::fromArray(
                $this->privateMessagePayload('/settings', fromId: $adminId),
            ),
            'private via-bot game share' => TelegramUpdate::fromArray(
                $this->privateViaBotGameMessagePayload('iq1'),
            ),
        ];
    }

    /**
     * @param AbstractProcessorHandler[] $handlers
     *
     * @return string[]
     */
    private function findMatchingHandlerNames(array $handlers, TelegramUpdate $update): array
    {
        $matched = [];

        foreach ($handlers as $handler) {
            if ($handler->matches($update)) {
                $matched[] = new ReflectionClass($handler)->getShortName();
            }
        }

        return $matched;
    }

    /**
     * @param array<string, string[]> $conflicts
     */
    private function formatConflicts(array $conflicts): string
    {
        $lines = [];

        foreach ($conflicts as $label => $handlerNames) {
            $lines[] = "- '$label' matched: " . implode(', ', $handlerNames);
        }

        return implode("\n", $lines);
    }
}
