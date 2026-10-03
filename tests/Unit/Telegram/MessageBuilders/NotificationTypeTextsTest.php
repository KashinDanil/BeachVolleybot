<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\NotificationTypeTexts;
use BeachVolleybot\User\NotificationType;
use PHPUnit\Framework\TestCase;

final class NotificationTypeTextsTest extends TestCase
{
    public function testLabelsReadAsInTheNotificationsMenu(): void
    {
        $this->assertSame(
            ['✅ Game is on', '⚠️ Short of players', "⬆️ You're in", "⬇️ You're out", '🕒 Game time changed'],
            array_map(
                static fn(NotificationType $type): string => NotificationTypeTexts::forType($type)->label,
                NotificationType::cases(),
            ),
        );
    }

    public function testEveryTypeSaysEachThingInItsOwnWords(): void
    {
        foreach (['label', 'trigger', 'descriptionFormat'] as $text) {
            $texts = array_map(
                static fn(NotificationType $type): string => NotificationTypeTexts::forType($type)->$text,
                NotificationType::cases(),
            );

            $this->assertSame($texts, array_unique($texts), "Two types share a $text");
        }
    }

    public function testEveryDescriptionFormatTakesTheKickoffDayAndTimeThenAnyLeadTime(): void
    {
        foreach (NotificationType::cases() as $type) {
            $texts = NotificationTypeTexts::forType($type);

            $this->assertSame(
                2 + $this->leadTimePlaceholders($texts),
                substr_count($texts->descriptionFormat, '%s'),
                "NotificationType::$type->name",
            );
        }
    }

    public function testATriggerTakesAPlaceholderOnlyForItsLeadTime(): void
    {
        foreach (NotificationType::cases() as $type) {
            $texts = NotificationTypeTexts::forType($type);

            $this->assertSame(
                $this->leadTimePlaceholders($texts),
                substr_count($texts->trigger, '%s'),
                "NotificationType::$type->name",
            );
        }
    }

    private function leadTimePlaceholders(NotificationTypeTexts $texts): int
    {
        if (null === $texts->leadTimeHours) {
            return 0;
        }

        return 1;
    }
}
