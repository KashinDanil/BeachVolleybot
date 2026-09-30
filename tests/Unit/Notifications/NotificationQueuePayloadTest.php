<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Notifications;

use BeachVolleybot\Notifications\NotificationQueuePayload;
use BeachVolleybot\User\NotificationType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationQueuePayloadTest extends TestCase
{
    public function testCarriesTheTypeGameAndUser(): void
    {
        $notificationPayload = new NotificationQueuePayload(NotificationType::PromotedIntoGame, 12, 200);

        $this->assertSame(NotificationType::PromotedIntoGame, $notificationPayload->type);
        $this->assertSame(12, $notificationPayload->gameId);
        $this->assertSame(200, $notificationPayload->userId);
    }

    public function testSerializesToTheQueuePayloadShape(): void
    {
        $this->assertSame(
            ['type' => 3, 'game_id' => 12, 'user_id' => 200],
            new NotificationQueuePayload(NotificationType::PromotedIntoGame, 12, 200)->jsonSerialize(),
        );
    }

    /** @return iterable<string, array{NotificationQueuePayload}> */
    public static function payloadsOfEveryType(): iterable
    {
        foreach (NotificationType::cases() as $type) {
            yield $type->name => [new NotificationQueuePayload($type, 12, 200)];
        }
    }

    #[DataProvider('payloadsOfEveryType')]
    public function testEveryTypeSurvivesAJsonRoundTrip(NotificationQueuePayload $notificationPayload): void
    {
        $this->assertEquals($notificationPayload, $this->serializeAndParse($notificationPayload));
    }

    public function testFromArrayIgnoresUnknownKeys(): void
    {
        $this->assertEquals(
            new NotificationQueuePayload(NotificationType::PromotedIntoGame, 12, 200),
            NotificationQueuePayload::fromArray(['type' => 3, 'game_id' => 12, 'user_id' => 200, 'extra' => 'ignored']),
        );
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'empty payload' => [[]];
        yield 'missing type' => [['game_id' => 12, 'user_id' => 200]];
        yield 'null type' => [['type' => null, 'game_id' => 12, 'user_id' => 200]];
        yield 'type as a numeric string' => [['type' => '3', 'game_id' => 12, 'user_id' => 200]];
        yield 'type as a float' => [['type' => 3.0, 'game_id' => 12, 'user_id' => 200]];
        yield 'type as a bool' => [['type' => true, 'game_id' => 12, 'user_id' => 200]];
        yield 'unknown type' => [['type' => 99, 'game_id' => 12, 'user_id' => 200]];
        yield 'type zero' => [['type' => 0, 'game_id' => 12, 'user_id' => 200]];
        yield 'missing game id' => [['type' => 3, 'user_id' => 200]];
        yield 'null game id' => [['type' => 3, 'game_id' => null, 'user_id' => 200]];
        yield 'game id as a string' => [['type' => 3, 'game_id' => '12', 'user_id' => 200]];
        yield 'game id as a float' => [['type' => 3, 'game_id' => 12.0, 'user_id' => 200]];
        yield 'missing user id' => [['type' => 3, 'game_id' => 12]];
        yield 'null user id' => [['type' => 3, 'game_id' => 12, 'user_id' => null]];
        yield 'user id as a string' => [['type' => 3, 'game_id' => 12, 'user_id' => '200']];
        yield 'user id as a float' => [['type' => 3, 'game_id' => 12, 'user_id' => 200.0]];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function testFromArrayReturnsNullForAnInvalidPayload(array $payload): void
    {
        $this->assertNull(NotificationQueuePayload::fromArray($payload));
    }

    private function serializeAndParse(NotificationQueuePayload $notificationPayload): ?NotificationQueuePayload
    {
        return NotificationQueuePayload::fromArray(json_decode(json_encode($notificationPayload), true));
    }
}
