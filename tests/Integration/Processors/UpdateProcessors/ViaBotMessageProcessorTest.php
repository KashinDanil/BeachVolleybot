<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Processors\UpdateProcessors\ViaBotMessageProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class ViaBotMessageProcessorTest extends ProcessorTestCase
{
    private const int CHAT_ID = -200;
    private const int MESSAGE_ID = 60;
    private const string INLINE_QUERY_ID = 'iq_via_bot';
    private const string INLINE_MESSAGE_ID = 'msg_via_bot';
    private const string PUMPKIN_TEXT =
        'This game has turned into a pumpkin 🎃 because this is an *unauthorized use* of the bot';

    public function testPinsTheMessageWhenChatIsAuthorized(): void
    {
        $this->authorizeChat(self::CHAT_ID);
        $this->seedInlineMessage();

        $this->process();

        $this->assertTrue($this->pinned());
        $this->assertNull($this->editedInlineMessageId());
        $this->assertSame(1, $this->storedAuthorized());
    }

    public function testRejectsTheMessageThroughItsInlineMessageIdWhenChatIsNotAuthorized(): void
    {
        $this->seedInlineMessage();

        $this->process();

        $this->assertFalse($this->pinned());
        $this->assertSame(self::INLINE_MESSAGE_ID, $this->editedInlineMessageId());
        $this->assertSame(self::PUMPKIN_TEXT, $this->editedText());
        $this->assertSame(0, $this->storedAuthorized());
    }

    public function testPinsAndDefersWhenRowIsMissing(): void
    {
        $this->process();

        // The row hasn't been written yet, so the decision defers to the edit check; the message is pinned meanwhile.
        $this->assertTrue($this->pinned());
        $this->assertNull($this->editedInlineMessageId());
    }

    private function process(): void
    {
        new ViaBotMessageProcessor($this->telegramSender)->process(TelegramUpdate::fromArray($this->payload()));
    }

    private function seedInlineMessage(): void
    {
        $gameId = $this->createGame(inlineMessageId: 'msg_seed', gameKey: 'query_1');
        new GameMessageRepository($this->db)->addInlineMessage($gameId, self::INLINE_MESSAGE_ID, self::INLINE_QUERY_ID);
    }

    private function pinned(): bool
    {
        foreach ($this->bot->calls as $call) {
            if ('call' === $call['method'] && 'pinChatMessage' === ($call['args'][0] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** editInlineMessage sends null chat/message id and the inline_message_id as the last arg. */
    private function editedInlineMessageId(): ?string
    {
        foreach ($this->bot->calls as $call) {
            if ('editMessageText' === $call['method']) {
                return $call['args'][6] ?? null;
            }
        }

        return null;
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
            'message' => [
                'message_id' => self::MESSAGE_ID,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => self::CHAT_ID, 'type' => 'group'],
                'date' => 1700000000,
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
