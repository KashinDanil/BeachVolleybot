<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class JoinWithTimeProcessorTest extends ProcessorTestCase
{
    public function testNewPlayerJoinsWithTime(): void
    {
        $gameId = $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('15:30', 'query_1');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);
        $this->assertSame('15:30', $gamePlayer['time']);
    }

    public function testNewPlayerGetsSlot(): void
    {
        $gameId = $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('15:30', 'query_1');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(200, (int)$slots[0]['telegram_user_id']);
    }

    public function testExistingPlayerUpdatesTime(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('16:00', 'query_1');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame('16:00', $gamePlayer['time']);
    }

    public function testExistingPlayerDoesNotGetExtraSlot(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('16:00', 'query_1');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    public function testDeletesUserMessage(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('15:30', 'query_1');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $deleteCalls = array_filter($this->bot->calls, fn($c) => 'deleteMessage' === $c['method']);
        $this->assertNotEmpty($deleteCalls);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('16:00', 'query_1');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testReactsConfusedWhenNoTime(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('no time here', 'query_1');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $this->assertReactedWithConfused();
        $this->assertMessageNotEdited();
    }

    public function testReactsConfusedWhenGameNotFound(): void
    {
        $update = $this->buildUpdate('15:30', 'unknown_query');

        new JoinWithTimeProcessor($this->telegramSender)->process($update);

        $this->assertReactedWithConfused();
        $this->assertMessageNotEdited();
    }

    public function testReactsConfusedWhenNoReplyMarkup(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $payload = [
            'update_id' => 1,
            'message' => [
                'message_id' => 54,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => -100, 'type' => 'group'],
                'date' => 1700000000,
                'text' => '15:30',
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => -100, 'type' => 'group'],
                    'date' => 1699999000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                ],
            ],
        ];

        new JoinWithTimeProcessor($this->telegramSender)->process(TelegramUpdate::fromArray($payload));

        $this->assertReactedWithConfused();
        $this->assertMessageNotEdited();
    }

    private function buildUpdate(string $text, string $inlineQueryId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->replyMessagePayload($text, $inlineQueryId),
        );
    }
}
