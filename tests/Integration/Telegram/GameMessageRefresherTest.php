<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Telegram;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\GameMessageRefresher;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use RuntimeException;

final class GameMessageRefresherTest extends ProcessorTestCase
{
    public function testEditsInlineMessage(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_42', title: 'Game 18:00');

        new GameMessageRefresher($this->telegramSender)->refresh($gameId);

        $this->assertMessageEdited();
    }

    public function testEditsEveryAttachedInlineMessage(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_a', title: 'Game 18:00');
        $this->attachInlineMessage($gameId, 'msg_b');
        $this->attachInlineMessage($gameId, 'msg_c');

        new GameMessageRefresher($this->telegramSender)->refresh($gameId);

        $editedIds = array_map(
            fn (array $call) => $call['args'][6],
            array_filter($this->bot->calls, fn (array $call) => 'editMessageText' === $call['method']),
        );
        $this->assertSame(['msg_a', 'msg_b', 'msg_c'], array_values($editedIds));
    }

    public function testRefreshRecordsEditsEveryGame(): void
    {
        $records = [
            $this->seedRecord('msg_a', 'query_a'),
            $this->seedRecord('msg_b', 'query_b'),
        ];

        new GameMessageRefresher($this->telegramSender)->refreshRecords($records);

        $this->assertSame(['msg_a', 'msg_b'], $this->editedInlineMessageIds());
    }

    public function testRefreshRecordsSendsNothingForAnEmptyList(): void
    {
        new GameMessageRefresher($this->telegramSender)->refreshRecords([]);

        $this->assertSame([], $this->editedInlineMessageIds());
    }

    public function testOneUnbuildableGameDoesNotStopTheOthers(): void
    {
        $broken = $this->seedRecord('msg_broken', 'query_broken');
        $healthy = $this->seedRecord('msg_healthy', 'query_healthy');

        $this->refresherFailingFor($broken->gameId)->refreshRecords([$broken, $healthy]);

        $this->assertSame(['msg_healthy'], $this->editedInlineMessageIds());
    }

    private function refresherFailingFor(int $gameId): GameMessageRefresher
    {
        return new readonly class($this->telegramSender, $gameId) extends GameMessageRefresher {
            public function __construct(TelegramMessageSender $sender, private int $failingGameId)
            {
                parent::__construct($sender);
            }

            public function refreshGame(GameInterface $game): void
            {
                if ($this->failingGameId === $game->getGameId()) {
                    throw new RuntimeException('cannot build game ' . $game->getGameId());
                }

                parent::refreshGame($game);
            }
        };
    }

    /** The records already carry the row, so building each game must not read games again. */
    public function testRefreshRecordsDoesNotReReadTheGameRow(): void
    {
        $records = [$this->seedRecord('msg_a', 'query_a')];

        $queries = $this->queriesDuring(
            fn() => new GameMessageRefresher($this->telegramSender)->refreshRecords($records),
        );

        $gameReads = array_filter($queries, fn(string $sql) => str_contains($sql, 'FROM "games"'));
        $this->assertSame([], array_values($gameReads));
    }

    private function seedRecord(string $inlineMessageId, string $gameKey): GameRecord
    {
        $gameId = $this->seedFullGame(inlineMessageId: $inlineMessageId, gameKey: $gameKey, title: 'Game 18:00');
        $record = new GameManager()->findGameRecordById($gameId);
        $this->assertNotNull($record);

        return $record;
    }

    /** @return list<string> */
    private function editedInlineMessageIds(): array
    {
        return array_values(array_map(
            fn(array $call) => $call['args'][6],
            array_filter($this->bot->calls, fn(array $call) => 'editMessageText' === $call['method']),
        ));
    }
}
