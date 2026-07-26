<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\BotMention;
use PHPUnit\Framework\TestCase;

final class BotMentionTest extends TestCase
{
    public function testIsPresentDetectsMention(): void
    {
        $this->assertTrue(BotMention::isPresent('@test_bot Bogatell 18:00'));
    }

    public function testIsPresentIsFalseWithoutMention(): void
    {
        $this->assertFalse(BotMention::isPresent('Bogatell 18:00'));
    }

    public function testStripRemovesLeadingMentionAndPreservesNewlines(): void
    {
        $this->assertSame(
            "📅 Saturday\n🏖️ Bogatell\n🕙 10:00",
            BotMention::strip("@test_bot \n📅 Saturday\n🏖️ Bogatell\n🕙 10:00"),
        );
    }

    public function testStripMidTextMentionLeavesASingleSpace(): void
    {
        $this->assertSame(
            'Volleyball 31.12.2099 18:00',
            BotMention::strip('Volleyball @test_bot 31.12.2099 18:00'),
        );
    }
}
