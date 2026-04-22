<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\PinnedMessageRepository;
use DateTimeImmutable;

final class PinnedMessageRepositoryTest extends DatabaseTestCase
{
    private PinnedMessageRepository $repository;

    private string $todayMidnight;

    private string $yesterdayMidnight;

    private string $tomorrowMidnight;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/002_create_pinned_messages.sql');
        $this->db->pdo->exec($schema);

        $this->repository = new PinnedMessageRepository($this->db);

        $today = new DateTimeImmutable('today');
        $this->todayMidnight = $today->format('Y-m-d H:i:s');
        $this->yesterdayMidnight = $today->modify('-1 day')->format('Y-m-d H:i:s');
        $this->tomorrowMidnight = $today->modify('+1 day')->format('Y-m-d H:i:s');
    }

    public function testCreateInsertsRow(): void
    {
        $this->repository->create(1, 100, '{"foo":"bar"}', $this->tomorrowMidnight);

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertCount(1, $rows);
        $this->assertSame(100, (int)$rows[0]['message_id']);
        $this->assertSame('{"foo":"bar"}', $rows[0]['message_json']);
        $this->assertSame($this->tomorrowMidnight, $rows[0]['unpin_after']);
    }

    public function testDeleteRemovesSingleRow(): void
    {
        $this->repository->create(1, 100, '{}', $this->tomorrowMidnight);
        $this->repository->create(1, 101, '{}', $this->tomorrowMidnight);

        $this->repository->delete(1, 100);

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertCount(1, $rows);
        $this->assertSame(101, (int)$rows[0]['message_id']);
    }

    public function testDeleteManyRemovesAllListedIds(): void
    {
        $this->repository->create(1, 100, '{}', $this->tomorrowMidnight);
        $this->repository->create(1, 101, '{}', $this->tomorrowMidnight);
        $this->repository->create(1, 102, '{}', $this->tomorrowMidnight);

        $this->repository->deleteMany(1, [100, 102]);

        $remaining = array_map(
            static fn(array $row): int => (int)$row['message_id'],
            $this->db->select('pinned_messages', ['message_id'], ['chat_id' => 1]),
        );
        $this->assertSame([101], $remaining);
    }

    public function testDeleteManyWithEmptyListIsNoop(): void
    {
        $this->repository->create(1, 100, '{}', $this->tomorrowMidnight);

        $this->repository->deleteMany(1, []);

        $this->assertCount(1, $this->db->select('pinned_messages', '*', ['chat_id' => 1]));
    }

    public function testFindExpiredIdsIncludesRowWithUnpinAfterEqualToTodayMidnight(): void
    {
        $this->repository->create(1, 100, '{}', $this->todayMidnight);

        $expired = $this->repository->findExpiredIds(1, 999);

        $this->assertSame([100], $expired);
    }

    public function testFindExpiredIdsIncludesRowWithUnpinAfterStrictlyInPast(): void
    {
        $this->repository->create(1, 100, '{}', $this->yesterdayMidnight);

        $expired = $this->repository->findExpiredIds(1, 999);

        $this->assertSame([100], $expired);
    }

    public function testFindExpiredIdsExcludesRowWithUnpinAfterInFuture(): void
    {
        $this->repository->create(1, 100, '{}', $this->tomorrowMidnight);

        $this->assertSame([], $this->repository->findExpiredIds(1, 999));
    }

    public function testFindExpiredIdsExcludesRowWithNullUnpinAfter(): void
    {
        $this->repository->create(1, 100, '{}', null);

        $this->assertSame([], $this->repository->findExpiredIds(1, 999));
    }

    public function testFindExpiredIdsExcludesExcludedMessageId(): void
    {
        $this->repository->create(1, 100, '{}', $this->yesterdayMidnight);
        $this->repository->create(1, 101, '{}', $this->yesterdayMidnight);

        $expired = $this->repository->findExpiredIds(1, 100);

        $this->assertSame([101], $expired);
    }

    public function testFindExpiredIdsScopedByChatId(): void
    {
        $this->repository->create(1, 100, '{}', $this->yesterdayMidnight);
        $this->repository->create(2, 200, '{}', $this->yesterdayMidnight);

        $expired = $this->repository->findExpiredIds(1, 999);

        $this->assertSame([100], $expired);
    }
}