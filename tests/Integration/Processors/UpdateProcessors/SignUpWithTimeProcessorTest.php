<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\SignUpWithTimeProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class SignUpWithTimeProcessorTest extends ProcessorTestCase
{
    public function testNewPlayerSignsUpWithTime(): void
    {
        $gameId = $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('15:30', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);
        $this->assertSame('15:30', $gamePlayer['time']);
    }

    public function testNewPlayerGetsSlot(): void
    {
        $gameId = $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('15:30', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(200, (int) $slots[0]['telegram_user_id']);
    }

    public function testExistingPlayerUpdatesTime(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('16:00', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame('16:00', $gamePlayer['time']);
    }

    public function testExistingPlayerDoesNotGetExtraSlot(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('16:00', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
    }

    public function testDeletesUserMessage(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('15:30', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $deleteCalls = array_filter($this->botCalls, fn($c) => 'deleteMessage' === $c['method']);
        $this->assertNotEmpty($deleteCalls);
    }

    public function testSetsReactionBeforeDeleting(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('15:30', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $reactionCalls = array_filter($this->botCalls, fn($c) => 'call' === $c['method'] && 'setMessageReaction' === ($c['args'][0] ?? null));
        $this->assertNotEmpty($reactionCalls);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('16:00', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $this->assertMessageEdited();
    }

    public function testIgnoresMessageWithoutTime(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate('no time here', 'query_1');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $this->assertEmpty($this->botCalls);
    }

    public function testIgnoresWhenGameNotFound(): void
    {
        $update = $this->buildUpdate('15:30', 'unknown_query');

        new SignUpWithTimeProcessor($this->bot)->process($update);

        $this->assertEmpty($this->botCalls);
    }

    public function testIgnoresMessageWithoutReplyMarkup(): void
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
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
                ],
            ],
        ];

        new SignUpWithTimeProcessor($this->bot)->process(TelegramUpdate::fromArray($payload));

        $this->assertEmpty($this->botCalls);
    }

    private function buildUpdate(string $text, string $inlineQueryId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->replyMessagePayload($text, $inlineQueryId),
        );
    }
}
