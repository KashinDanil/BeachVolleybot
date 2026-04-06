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
        $gameId = $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->telegramSender)->process($update);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('41.399747,2.20778', $game['location']);
    }

    public function testReactsWithCheckmark(): void
    {
        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->telegramSender)->process($update);

        $this->assertReactedWith('👍');
    }

    public function testDoesNotDeleteMessage(): void
    {
        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->telegramSender)->process($update);

        $deleteCalls = array_filter($this->bot->calls, fn($c) => 'deleteMessage' === $c['method']);
        $this->assertEmpty($deleteCalls);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testReactsConfusedWhenPlayerNotInGame(): void
    {
        $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->telegramSender)->process($update);

        $this->assertMessageNotEdited();
    }

    public function testDoesNotUpdateLocationWhenPlayerNotInGame(): void
    {
        $gameId = $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate(41.399747, 2.207780, 'query_1');

        new SetLocationProcessor($this->telegramSender)->process($update);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertNull($game['location']);
    }

    public function testIgnoresWhenGameNotFound(): void
    {
        $update = $this->buildUpdate(41.399747, 2.207780, 'unknown_query');

        new SetLocationProcessor($this->telegramSender)->process($update);

        $this->assertEmpty($this->bot->calls);
    }

    public function testIgnoresMessageWithoutLocation(): void
    {
        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = TelegramUpdate::fromArray(
            $this->replyMessagePayload('hello', 'query_1'),
        );

        new SetLocationProcessor($this->telegramSender)->process($update);

        $this->assertEmpty($this->bot->calls);
    }

    public function testIgnoresMessageWithoutReplyMarkup(): void
    {
        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
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

        new SetLocationProcessor($this->telegramSender)->process(TelegramUpdate::fromArray($payload));

        $this->assertEmpty($this->bot->calls);
    }

    private function buildUpdate(float $latitude, float $longitude, string $inlineQueryId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->locationMessagePayload($latitude, $longitude, $inlineQueryId),
        );
    }
}
