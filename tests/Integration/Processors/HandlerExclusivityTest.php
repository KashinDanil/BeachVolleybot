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
                $this->inlineQueryPayload(gameKey: 'iq1', query: 'Beach Volleyball 31.12.2099 18:00'),
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
                $this->editedLocationMessagePayload(latitude: 41.4, longitude: 2.2, gameKey: 'iq1'),
            ),
            'reply with location (game queue)' => TelegramUpdate::fromArray(
                $this->locationMessagePayload(latitude: 41.4, longitude: 2.2, gameKey: 'iq1'),
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
            'group @mention new game' => TelegramUpdate::fromArray([
                'update_id' => 1,
                'message' => [
                    'message_id' => 70,
                    'from' => ['id' => $nonAdminId, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => -100, 'type' => 'group'],
                    'date' => 1700000000,
                    'text' => '@' . BOT_USERNAME . ' Bogatell 31.12.2099 18:00',
                ],
            ]),
            'private @mention new game' => TelegramUpdate::fromArray([
                'update_id' => 1,
                'message' => [
                    'message_id' => 71,
                    'from' => ['id' => $nonAdminId, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => $nonAdminId, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => '@' . BOT_USERNAME . ' Bogatell 31.12.2099 18:00',
                ],
            ]),
            // A game button on the bot's own chat message (group or DM) — no
            // inline_message_id, resolved from the callback's message instead.
            'chat game message callback' => TelegramUpdate::fromArray([
                'update_id' => 1,
                'callback_query' => [
                    'id' => 'cbq_2',
                    'from' => ['id' => $nonAdminId, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat_instance' => '-123',
                    'message' => [
                        'message_id' => 88,
                        'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                        'chat' => ['id' => -100, 'type' => 'group'],
                        'date' => 1700000000,
                    ],
                    'data' => json_encode(['a' => 'j']),
                ],
            ]),
            // A command sent as a reply to a non-game bot message (e.g. the games
            // list) must reach the command handler, not a reply-based game handler.
            'dm command as reply to non-game bot message' => TelegramUpdate::fromArray([
                'update_id' => 1,
                'message' => [
                    'message_id' => 73,
                    'from' => ['id' => $nonAdminId, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => $nonAdminId, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => '/games',
                    'reply_to_message' => [
                        'message_id' => 72,
                        'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                        'chat' => ['id' => $nonAdminId, 'type' => 'private'],
                        'date' => 1699999000,
                    ],
                ],
            ]),
            // /new_game arrives ephemeral in a group (visible only to the sender).
            'group ephemeral /new_game' => TelegramUpdate::fromArray([
                'update_id' => 1,
                'message' => [
                    'message_id' => 0,
                    'ephemeral_message_id' => 500,
                    'from' => ['id' => $nonAdminId, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => -100, 'type' => 'group'],
                    'date' => 1700000000,
                    'text' => '/new_game@' . BOT_USERNAME,
                    'receiver_user' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                ],
            ]),
            'private /new_game' => TelegramUpdate::fromArray([
                'update_id' => 1,
                'message' => [
                    'message_id' => 74,
                    'from' => ['id' => $nonAdminId, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => $nonAdminId, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => '/new_game',
                ],
            ]),
            // A /new_game wizard button press: the ephemeral wizard message carries a
            // callback with the distinct "na" key that only the wizard handler owns.
            'new_game wizard callback' => TelegramUpdate::fromArray([
                'update_id' => 1,
                'callback_query' => [
                    'id' => 'cbq_ng',
                    'from' => ['id' => $nonAdminId, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat_instance' => '-123',
                    'message' => [
                        'message_id' => 0,
                        'ephemeral_message_id' => 501,
                        'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                        'chat' => ['id' => -100, 'type' => 'group'],
                        'date' => 1700000000,
                    ],
                    'data' => json_encode(['na' => 'd', 'd' => '2099-12-31']),
                ],
            ]),
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
