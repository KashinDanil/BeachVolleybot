<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\Handlers\GameHandlers\CreateGameFromMessageHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use PHPUnit\Framework\TestCase;

final class CreateGameFromMessageHandlerTest extends TestCase
{
    private CreateGameFromMessageHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new CreateGameFromMessageHandler();
    }

    public function testMatchesGroupMentionWithDateAndTime(): void
    {
        $this->assertTrue($this->handler->matches($this->groupMessage('@test_bot Bogatell 31.12.2099 10:00')));
    }

    public function testDoesNotMatchPlainMentionWithoutDateOrTime(): void
    {
        $this->assertFalse($this->handler->matches($this->groupMessage("hey @test_bot let's play")));
    }

    public function testDoesNotMatchWithoutMention(): void
    {
        $this->assertFalse($this->handler->matches($this->groupMessage('Bogatell 31.12.2099 10:00')));
    }

    public function testDoesNotMatchReply(): void
    {
        $this->assertFalse($this->handler->matches($this->groupMessageReply('@test_bot Bogatell 31.12.2099 10:00')));
    }

    public function testMatchesPrivateMention(): void
    {
        $this->assertTrue($this->handler->matches($this->privateMessage('@test_bot Bogatell 31.12.2099 10:00')));
    }

    public function testMatchesMentionSentInAForumTopic(): void
    {
        // A message merely sent in a topic auto-carries reply_to_message pointing at
        // the topic-creation message — that is not a real reply, so it must match.
        $this->assertTrue($this->handler->matches($this->topicMessage('@test_bot Bogatell 31.12.2099 10:00')));
    }

    public function testDoesNotMatchRealReplyInAForumTopic(): void
    {
        $this->assertFalse($this->handler->matches($this->topicReply('@test_bot Bogatell 31.12.2099 10:00')));
    }

    public function testRoutesToPerChatCreationQueue(): void
    {
        $update = $this->groupMessage('@test_bot Bogatell 31.12.2099 10:00', chatId: -100);

        $this->assertSame('game_new_-100', $this->handler->routeToQueue($update));
    }

    private function groupMessage(string $text, int $chatId = -100): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 5,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => $chatId, 'type' => 'group'],
                'date' => 1700000000,
                'text' => $text,
            ],
        ]);
    }

    private function groupMessageReply(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 5,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => -100, 'type' => 'group'],
                'date' => 1700000000,
                'text' => $text,
                'reply_to_message' => [
                    'message_id' => 4,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => -100, 'type' => 'group'],
                    'date' => 1699999000,
                ],
            ],
        ]);
    }

    private function privateMessage(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 5,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => 200, 'type' => 'private'],
                'date' => 1700000000,
                'text' => $text,
            ],
        ]);
    }

    // A first message in a forum topic: not a reply, but Telegram fills
    // reply_to_message with the topic-creation service message.
    private function topicMessage(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 510,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => -1003759398496, 'type' => 'supergroup', 'is_forum' => true],
                'date' => 1785068131,
                'message_thread_id' => 328,
                'text' => $text,
                'is_topic_message' => true,
                'reply_to_message' => [
                    'message_id' => 328,
                    'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => -1003759398496, 'type' => 'supergroup', 'is_forum' => true],
                    'date' => 1776972899,
                    'message_thread_id' => 328,
                    'forum_topic_created' => ['name' => 'New topic here', 'icon_color' => 13338331],
                    'is_topic_message' => true,
                ],
            ],
        ]);
    }

    // A genuine reply inside a topic: reply_to_message is a real message, no
    // forum_topic_created.
    private function topicReply(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 511,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => -1003759398496, 'type' => 'supergroup', 'is_forum' => true],
                'date' => 1785068385,
                'message_thread_id' => 328,
                'text' => $text,
                'is_topic_message' => true,
                'reply_to_message' => [
                    'message_id' => 510,
                    'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => -1003759398496, 'type' => 'supergroup', 'is_forum' => true],
                    'date' => 1785068131,
                    'message_thread_id' => 328,
                    'text' => 'earlier message',
                    'is_topic_message' => true,
                ],
            ],
        ]);
    }
}
