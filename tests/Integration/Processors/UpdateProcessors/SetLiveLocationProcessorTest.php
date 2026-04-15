<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class SetLiveLocationProcessorTest extends ProcessorTestCase
{
    public function testUpdatesGameLocation(): void
    {
        $gameId = $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.413023, 2.194859, 'query_1');

        new SetLiveLocationProcessor($this->telegramSender)->process($update);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('41.413023,2.194859', $game['location']);
    }

    private function buildUpdate(float $latitude, float $longitude, string $inlineQueryId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->editedLocationMessagePayload($latitude, $longitude, $inlineQueryId),
        );
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.413023, 2.194859, 'query_1');

        new SetLiveLocationProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testThrottlesWithinFiveSeconds(): void
    {
        $gameId = $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $firstUpdate = $this->buildUpdate(41.413023, 2.194859, 'query_1');
        $secondUpdate = $this->buildUpdate(41.414000, 2.195000, 'query_1');

        $processor = new SetLiveLocationProcessor($this->telegramSender);
        $processor->process($firstUpdate);
        $processor->process($secondUpdate);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertSame('41.413023,2.194859', $game['location']);
    }

    public function testDoesNotReact(): void
    {
        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.413023, 2.194859, 'query_1');

        new SetLiveLocationProcessor($this->telegramSender)->process($update);

        $reactionCalls = array_filter($this->bot->calls, fn($c) => 'call' === $c['method'] && 'setMessageReaction' === ($c['args'][0] ?? null));
        $this->assertEmpty($reactionCalls);
    }

    public function testIgnoresWhenPlayerNotInGame(): void
    {
        $gameId = $this->seedFullGame(inlineQueryId: 'query_1');
        $update = $this->buildUpdate(41.413023, 2.194859, 'query_1');

        new SetLiveLocationProcessor($this->telegramSender)->process($update);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertNull($game['location']);
    }

    public function testLogsUserAction(): void
    {
        $logFile = BASE_LOG_DIR . '/user_actions.log';
        @unlink($logFile);

        $this->seedGameWithPlayer(inlineMessageId: 'msg_1');
        $update = $this->buildUpdate(41.413023, 2.194859, 'query_1');

        new SetLiveLocationProcessor($this->telegramSender)->process($update);

        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString("action='update_live_location'", $logContent);
        $this->assertStringContainsString('location=41.413023,2.194859', $logContent);
    }

    public function testIgnoresWhenGameNotFound(): void
    {
        $update = $this->buildUpdate(41.413023, 2.194859, 'unknown_query');

        new SetLiveLocationProcessor($this->telegramSender)->process($update);

        $this->assertEmpty($this->bot->calls);
    }

    protected function setUp(): void
    {
        parent::setUp();
        SetLiveLocationProcessor::resetThrottle();
    }
}
