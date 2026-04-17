<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\MessagePinManager;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use DateTimeImmutable;

final class MessagePinManagerTest extends DatabaseTestCase
{
    private MessagePinManager $manager;

    public function testRegisterSetsUnpinAfterToNextDayMidnightWhenDateInTitle(): void
    {
        $messageDate = (new DateTimeImmutable('2026-04-17 12:00:00'))->getTimestamp();

        $this->manager->register(1, 42, '{}', 'Beach 17.04 18:00', $messageDate);

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertCount(1, $rows);
        $this->assertSame('2026-04-18 00:00:00', $rows[0]['unpin_after']);
    }

    public function testRegisterSetsUnpinAfterToNextDayMidnightForDayOfWeekTitle(): void
    {
        $messageDate = (new DateTimeImmutable('2026-04-15 10:00:00'))->getTimestamp(); // Wednesday

        $this->manager->register(1, 43, '{}', 'Friday 18:00', $messageDate);

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertCount(1, $rows);
        $this->assertSame('2026-04-18 00:00:00', $rows[0]['unpin_after']);
    }

    // --- register: unpin_after computation ---

    public function testRegisterSetsUnpinAfterNullWhenNoDateInTitle(): void
    {
        $messageDate = (new DateTimeImmutable('2026-04-17 12:00:00'))->getTimestamp();

        $this->manager->register(1, 44, '{}', 'Beach game 18:00', $messageDate);

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['unpin_after']);
    }

    public function testRegisterUnpinAfterIsAlwaysMidnightRegardlessOfEventTime(): void
    {
        $messageDate = (new DateTimeImmutable('2026-04-17 23:59:00'))->getTimestamp();

        $this->manager->register(1, 45, '{}', 'Beach 17.04 23:30', $messageDate);

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertSame('2026-04-18 00:00:00', $rows[0]['unpin_after']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/002_create_pinned_messages.sql');
        $this->db->pdo->exec($schema);

        Connection::set($this->db);
        $this->manager = new MessagePinManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }
}