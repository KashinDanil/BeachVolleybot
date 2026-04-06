<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Processors\UpdateProcessors\SetLocationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class SetLocationProcessorTest extends ProcessorTestCase
{
    public function testUpdatesGameLocation(): void
    {
        $gameId = $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->bot)->process($update);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('41.399747,2.20778', $game['location']);
    }

    public function testDeletesUserMessage(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->bot)->process($update);

        $deleteCalls = array_filter($this->bot->calls, fn($c) => 'deleteMessage' === $c['method']);
        $this->assertNotEmpty($deleteCalls);
    }

    public function testSetsReaction(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->bot)->process($update);

        $reactionCalls = array_filter($this->bot->calls, fn($c) => 'call' === $c['method'] && 'setMessageReaction' === ($c['args'][0] ?? null));
        $this->assertNotEmpty($reactionCalls);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->bot)->process($update);

        $this->assertMessageEdited();
    }

    public function testIgnoresWhenGameNotFound(): void
    {
        $update = $this->buildUpdate(41.399747, 2.207780, 'unknown_query');

        new SetLocationProcessor($this->bot)->process($update);

        $this->assertEmpty($this->bot->calls);
    }

    public function testIgnoresMessageWithoutLocation(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = TelegramUpdate::fromArray(
            $this->replyMessagePayload('hello', 'query_1'),
        );

        new SetLocationProcessor($this->bot)->process($update);

        $this->assertEmpty($this->bot->calls);
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
                'location' => ['latitude' => 41.399747, 'longitude' => 2.207780],
                'reply_to_message' => [
                    'message_id' => 53,
                    'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat' => ['id' => -100, 'type' => 'group'],
                    'date' => 1699999000,
                    'via_bot' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => BOT_USERNAME],
                ],
            ],
        ];

        new SetLocationProcessor($this->bot)->process(TelegramUpdate::fromArray($payload));

        $this->assertEmpty($this->bot->calls);
    }

    private function buildUpdate(float $latitude, float $longitude, string $inlineQueryId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->locationMessagePayload($latitude, $longitude, $inlineQueryId),
        );
    }
}
