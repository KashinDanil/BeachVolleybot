<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Messages\Incoming;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use PHPUnit\Framework\TestCase;

final class TelegramUpdateTest extends TestCase
{
    private const array SENDER       = ['id' => 555, 'first_name' => 'Danil', 'is_bot' => false];
    private const array BOT          = ['id' => 999, 'first_name' => 'Bot', 'is_bot' => true];
    private const array PRIVATE_CHAT = ['id' => 555, 'first_name' => 'Danil', 'type' => 'private'];
    private const array GROUP_CHAT   = ['id' => -100, 'title' => 'Beach', 'type' => 'supergroup'];

    public function testAMessageUpdateExposesItsMessageChatAndSender(): void
    {
        $update = TelegramUpdate::fromArray(['update_id' => 1, 'message' => $this->message(self::PRIVATE_CHAT)]);

        $this->assertSame($update->message, $update->getMessage());
        $this->assertSame(555, $update->getChat()?->id);
        $this->assertSame(555, $update->getFrom()?->id);
    }

    public function testAnEditedMessageUpdateExposesItsMessageChatAndSender(): void
    {
        $update = TelegramUpdate::fromArray(['update_id' => 1, 'edited_message' => $this->message(self::GROUP_CHAT)]);

        $this->assertSame($update->editedMessage, $update->getMessage());
        $this->assertSame(-100, $update->getChat()?->id);
        $this->assertSame(555, $update->getFrom()?->id);
    }

    public function testACallbackOnAChatMessageUsesThePresserAndTheMessageChat(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq',
                'from' => self::SENDER,
                'chat_instance' => '-1',
                'message' => ['message_id' => 2, 'from' => self::BOT, 'chat' => self::GROUP_CHAT, 'date' => 1700000000],
                'data' => '{}',
            ],
        ]);

        $this->assertNull($update->getMessage());
        $this->assertSame(-100, $update->getChat()?->id);
        $this->assertSame(555, $update->getFrom()?->id);
    }

    public function testACallbackOnAnInlineMessageHasNoChat(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq',
                'from' => self::SENDER,
                'chat_instance' => '-1',
                'inline_message_id' => 'inline_1',
                'data' => '{}',
            ],
        ]);

        $this->assertNull($update->getChat());
        $this->assertSame(555, $update->getFrom()?->id);
    }

    public function testAnInlineQueryHasASenderButNoChat(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'inline_query' => ['id' => 'iq', 'from' => self::SENDER, 'query' => '', 'offset' => ''],
        ]);

        $this->assertNull($update->getMessage());
        $this->assertNull($update->getChat());
        $this->assertSame(555, $update->getFrom()?->id);
    }

    public function testAChosenInlineResultHasASenderButNoChat(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'chosen_inline_result' => ['result_id' => 'r1', 'from' => self::SENDER, 'query' => ''],
        ]);

        $this->assertNull($update->getChat());
        $this->assertSame(555, $update->getFrom()?->id);
    }

    public function testAnUpdateWithoutAnyKnownPartHasNothing(): void
    {
        $update = TelegramUpdate::fromArray(['update_id' => 1]);

        $this->assertNull($update->getMessage());
        $this->assertNull($update->getChat());
        $this->assertNull($update->getFrom());
    }

    private function message(array $chat): array
    {
        return ['message_id' => 1, 'from' => self::SENDER, 'chat' => $chat, 'date' => 1700000000, 'text' => 'hi'];
    }
}
