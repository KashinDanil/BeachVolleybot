<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\MessagePinManager;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

final class MessagePinManagerTest extends DatabaseTestCase
{
    private MessagePinManager $manager;

    // --- register: unpin_after computation ---

    public function testRegisterSetsUnpinAfterToNextDayMidnight(): void
    {
        $this->manager->register(1, 42, '{}', $this->atTheVenue('2026-04-17 18:00:00'));

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertCount(1, $rows);
        // Stored as UTC: midnight at the venue on the 18th is 22:00Z on the 17th.
        $this->assertSame('2026-04-17 22:00:00', $rows[0]['unpin_after']);
    }

    public function testRegisterUnpinAfterIsAlwaysMidnightRegardlessOfEventTime(): void
    {
        $this->manager->register(1, 45, '{}', $this->atTheVenue('2026-04-17 23:30:00'));

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertSame('2026-04-17 22:00:00', $rows[0]['unpin_after']);
    }

    public function testRegisterSetsUnpinAfterNullWhenNoEventDateIsKnown(): void
    {
        $this->manager->register(1, 44, '{}', null);

        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => 1]);
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['unpin_after']);
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

    private function atTheVenue(string $wallClock): DateTimeImmutable
    {
        return new DateTimeImmutable($wallClock, KnownVenues::defaultVenue()->timezone);
    }
}
