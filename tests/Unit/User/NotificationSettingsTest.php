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

    public function testToIntEqualsTheExpectedBitCombination(): void
    {
        $settings = new NotificationSettings()->enable(NotificationType::GameShortBeforeKickoff);

        $this->assertSame(1 << NotificationType::GameShortBeforeKickoff->value, $settings->toInt());
    }
}
