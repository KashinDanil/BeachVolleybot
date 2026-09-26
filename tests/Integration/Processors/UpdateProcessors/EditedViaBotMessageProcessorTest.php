<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Processors\UpdateProcessors\EditedViaBotMessageProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class EditedViaBotMessageProcessorTest extends ProcessorTestCase
{
    private const int CHAT_ID = -200;
    private const int MESSAGE_ID = 745;
    private const string INLINE_QUERY_ID = 'iq_via_bot';
    private const string INLINE_MESSAGE_ID = 'msg_via_bot';
    private const string PUMPKIN_TEXT =
        'This game has turned into a pumpkin 🎃 because this is an *unauthorized use* of the bot';

    public function testMemoizesAuthorizationWhenChatIsAuthorized(): void
    {
        $this->authorizeChat(self::CHAT_ID);
        $this->seedInlineMessage();

        $this->process();

        $this->assertSame(1, $this->storedAuthorized());
        $this->assertNull($this->editedInlineMessageId());
    }

    public function testRejectsWhenChatIsNotAuthorized(): void
    {
        $this->seedInlineMessage();

        $this->process();

        $this->assertSame(0, $this->storedAuthorized());
        $this->assertSame(self::INLINE_MESSAGE_ID, $this->editedInlineMessageId());
        $this->assertSame(self::PUMPKIN_TEXT, $this->editedText());
        $this->assertTrue($this->unpinned());
    }

    /** The inline pin races the row write, so a rejected message may already be pinned when the edit settles it. */
    public function testUnpinsAPreviouslyPinnedMessageWhenRejected(): void
    {
        $this->seedInlineMessage();
        $this->pin(self::MESSAGE_ID);

        $this->process();

        $this->assertTrue($this->unpinned());
        $this->assertFalse(
            $this->db->has('pinned_messages', ['chat_id' => self::CHAT_ID, 'message_id' => self::MESSAGE_ID]),
        );
    }

    public function testAFailedHideStaysUndecidedSoTheNextEditRetries(): void
    {
        $this->seedInlineMessage();
        $this->bot->failEdit = true;

        $this->process();

        // The hide edit failed, so the memo must stay NULL — otherwise the live buttons would
        // never be hidden and the next edit would short-circuit on the memo.
        $this->assertNull($this->storedAuthorized());

        $this->bot->failEdit = false;
        $this->process();

        $this->assertSame(0, $this->storedAuthorized());
        $this->assertSame(self::INLINE_MESSAGE_ID, $this->editedInlineMessageId());
    }

    public function testSkipsWhenAlreadyDecided(): void
    {
        $this->seedInlineMessage();
        // Memo says authorized; the chat is not in the allowlist, so a re-check would reject it.
        new GameMessageRepository($this->db)->setAuthorizedByInlineQueryId(self::INLINE_QUERY_ID, true);

        $this->process();

        $this->assertSame(1, $this->storedAuthorized());
        $this->assertNull($this->editedInlineMessageId());
    }

    public function testSkipsWhenRowIsMissing(): void
    {
        $this->process();

        $this->assertNull($this->editedInlineMessageId());
    }

    private function process(): void
    {
        new EditedViaBotMessageProcessor($this->telegramSender)->process(TelegramUpdate::fromArray($this->payload()));
    }

    private function seedInlineMessage(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_seed', gameKey: 'query_1');
        new GameMessageRepository($this->db)->addInlineMessage($gameId, self::INLINE_MESSAGE_ID, self::INLINE_QUERY_ID);
    }

    private function editedInlineMessageId(): ?string
    {
        foreach ($this->bot->calls as $call) {
            if ('editMessageText' === $call['method']) {
                return $call['args'][6] ?? null;
            }
        }

        return null;
    }

    private function pin(int $messageId): void
    {
        $this->db->insert('pinned_messages', [
            'chat_id' => self::CHAT_ID,
            'message_id' => $messageId,
            'message_json' => '{}',
            'unpin_after' => null,
        ]);
    }

    private function unpinned(): bool
    {
        foreach ($this->bot->calls as $call) {
            if ('call' === $call['method'] && 'unpinChatMessage' === ($call['args'][0] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function storedAuthorized(): ?int
    {
        $value = $this->db->get('game_messages', 'authorized', ['inline_query_id' => self::INLINE_QUERY_ID]);

        return null === $value ? null : (int)$value;
    }

    private function payload(): array
    {
        return [
            'update_id' => 1,
            'edited_message' => [
                'message_id' => self::MESSAGE_ID,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup'],
                'date' => 1700000000,
                'edit_date' => 1700000100,
                'text' => 'Game body',
                'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Leave (−1)', 'callback_data' => json_encode(['a' => 'l', 'q' => 'query_1'])],
                            ['text' => 'Join (+1)', 'callback_data' => json_encode(['a' => 'j', 'i' => self::INLINE_QUERY_ID])],
                        ],
                    ],
                ],
            ],
        ];
    }
}
