<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\User;

use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use PHPUnit\Framework\TestCase;

final class NotificationSettingsTest extends TestCase
{
    public function testEmptySettingsHaveEveryTypeDisabled(): void
    {
        $settings = new NotificationSettings();

        $this->assertFalse($settings->isEnabled(NotificationType::GameReachedMinimumPlayers));
        $this->assertFalse($settings->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertFalse($settings->isEnabled(NotificationType::BumpedFromGame));
        $this->assertFalse($settings->isEnabled(NotificationType::GameShortBeforeKickoff));
    }

    public function testEnableSetsExactlyThatTypeAndLeavesTheOriginalAlone(): void
    {
        $original = new NotificationSettings();

        $enabled = $original->enable(NotificationType::PromotedIntoGame);

        $this->assertFalse($original->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertTrue($enabled->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertFalse($enabled->isEnabled(NotificationType::GameReachedMinimumPlayers));
    }

    public function testDisableClearsTheBit(): void
    {
        $enabled = new NotificationSettings()->enable(NotificationType::BumpedFromGame);

        $disabled = $enabled->disable(NotificationType::BumpedFromGame);

        $this->assertFalse($disabled->isEnabled(NotificationType::BumpedFromGame));
    }

    public function testEnablingASecondTypeKeepsTheFirst(): void
    {
        $settings = new NotificationSettings()
            ->enable(NotificationType::GameReachedMinimumPlayers)
            ->enable(NotificationType::GameShortBeforeKickoff);

        $this->assertTrue($settings->isEnabled(NotificationType::GameReachedMinimumPlayers));
        $this->assertTrue($settings->isEnabled(NotificationType::GameShortBeforeKickoff));
        $this->assertFalse($settings->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertFalse($settings->isEnabled(NotificationType::BumpedFromGame));
    }

    public function testFromIntAndToIntRoundTrip(): void
    {
        $mask = (1 << NotificationType::PromotedIntoGame->value) | (1 << NotificationType::BumpedFromGame->value);

        $settings = NotificationSettings::fromInt($mask);

        $this->assertSame($mask, $settings->toInt());
        $this->assertTrue($settings->isEnabled(NotificationType::PromotedIntoGame));
        $this->assertTrue($settings->isEnabled(NotificationType::BumpedFromGame));
        $this->assertFalse($settings->isEnabled(NotificationType::GameReachedMinimumPlayers));
    }

    public function testEveryTypeOwnsADistinctBitThatFitsASqliteInteger(): void
    {
        $combinedMask = 0;

        foreach (NotificationType::cases() as $type) {
            // Bit 63 is the sign bit of SQLite's signed 64-bit INTEGER.
            $this->assertTrue(
                0 <= $type->value && 63 > $type->value,
                "NotificationType::$type->name = $type->value is a bit position that does not fit the users.notifications column. "
                . 'Give it the next unused value between 0 and 62.',
            );
            $this->assertSame(
                0,
                $combinedMask & $type->bit(),
                "NotificationType::$type->name shares a bit with another type. Check NotificationType::bit().",
            );

            $combinedMask |= $type->bit();
        }
    }

    public function testEveryTypeTogglesWithoutTouchingTheOthers(): void
    {
        $allEnabled = new NotificationSettings();

        foreach (NotificationType::cases() as $type) {
            $allEnabled = $allEnabled->enable($type);
        }

        foreach (NotificationType::cases() as $toggledType) {
            $onlyThisEnabled = new NotificationSettings()->enable($toggledType);
            $allButThisEnabled = $allEnabled->disable($toggledType);

            foreach (NotificationType::cases() as $type) {
                $this->assertSame(
                    $type === $toggledType,
                    $onlyThisEnabled->isEnabled($type),
                    "Enabling NotificationType::$toggledType->name changed NotificationType::$type->name. Their bits overlap.",
                );
                $this->assertSame(
                    $type !== $toggledType,
                    $allButThisEnabled->isEnabled($type),
                    "Disabling NotificationType::$toggledType->name changed NotificationType::$type->name. Their bits overlap.",
                );
            }
        }
    }

    public function testToIntEqualsTheExpectedBitCombination(): void
    {
        $settings = new NotificationSettings()->enable(NotificationType::GameShortBeforeKickoff);

        $this->assertSame(1 << NotificationType::GameShortBeforeKickoff->value, $settings->toInt());
    }
}
