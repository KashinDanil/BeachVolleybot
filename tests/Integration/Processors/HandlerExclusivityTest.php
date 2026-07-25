<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Processors\AbstractProcessorHandler;
use BeachVolleybot\Processors\ProcessorRegistryFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use ReflectionClass;

// Handlers come straight from the routing table, so a new handler is picked up here for free.
// What is NOT free: add a representative update fixture in fixtures() for it, because
// exclusivity is enforced over those fixtures, not by static analysis.
final class HandlerExclusivityTest extends ProcessorTestCase
{
    private const int NON_ADMIN_ID = 999;

    public function testNoUpdateMatchesMoreThanOneHandler(): void
    {
        // Admin-gated handlers read the role from the DB, so the admin fixtures
        // only match once the canonical admin actually has the admin role.
        $this->seedAdmin();

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
        return array_merge(
            ProcessorRegistryFactory::immediateHandlers(),
            ProcessorRegistryFactory::queuedHandlers(),
        );
    }

    /**
     * @return array<string, TelegramUpdate>
     */
    private function fixtures(): array
    {
        $adminId = self::ADMIN_TELEGRAM_USER_ID;
        $nonAdminId = self::NON_ADMIN_ID;

        return [
            'inline query' => TelegramUpdate::fromArray(
                $this->inlineQueryPayload(inlineQueryId: 'iq1', query: 'Beach Volleyball 31.12.2099 18:00'),
            ),
            'chosen inline result (create game)' => TelegramUpdate::fromArray(
                $this->chosenInlineResultPayload(
                    inlineMessageId: 'imi1',
                    resultId: 'r1',
                    query: 'Beach Volleyball 31.12.2099 18:00',
                ),
            ),
            'chosen inline result (forward game)' => TelegramUpdate::fromArray(
                $this->chosenInlineResultPayload(
                    inlineMessageId: 'imi2',
                    resultId: 'r2',
                    query: ForwardGameQueryExtractor::PREFIX . ' 42',
                ),
            ),
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
            'private /help' => TelegramUpdate::fromArray(
                $this->privateMessagePayload('/help', fromId: $nonAdminId),
            ),
            'private /start (help alias)' => TelegramUpdate::fromArray(
                $this->privateMessagePayload('/start', fromId: $nonAdminId),
            ),
            'group ephemeral /help' => TelegramUpdate::fromArray(
                $this->ephemeralGroupMessagePayload(fromId: $nonAdminId),
            ),
            // A forum-topic command carries a reply_to_message, so this fixture also pins
            // down that the reply-based game handlers stay out of the way.
            'group plain /help' => TelegramUpdate::fromArray(
                $this->ephemeralGroupMessagePayload(fromId: $nonAdminId, ephemeralMessageId: null),
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
