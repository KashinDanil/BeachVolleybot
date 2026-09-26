<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Messages\Incoming;

use BeachVolleybot\Telegram\Messages\Incoming\ChatMemberStatus;
use PHPUnit\Framework\TestCase;

final class ChatMemberStatusTest extends TestCase
{
    public function testCreatorAdministratorAndMemberArePresent(): void
    {
        $this->assertTrue(ChatMemberStatus::Creator->isPresent());
        $this->assertTrue(ChatMemberStatus::Administrator->isPresent());
        $this->assertTrue(ChatMemberStatus::Member->isPresent());

        $this->assertFalse(ChatMemberStatus::Creator->hasLeft());
        $this->assertFalse(ChatMemberStatus::Administrator->hasLeft());
        $this->assertFalse(ChatMemberStatus::Member->hasLeft());
    }

    public function testLeftAndKickedHaveLeft(): void
    {
        $this->assertTrue(ChatMemberStatus::Left->hasLeft());
        $this->assertTrue(ChatMemberStatus::Kicked->hasLeft());

        $this->assertFalse(ChatMemberStatus::Left->isPresent());
        $this->assertFalse(ChatMemberStatus::Kicked->isPresent());
    }

    public function testRestrictedIsNeitherPresentNorLeft(): void
    {
        $this->assertFalse(ChatMemberStatus::Restricted->isPresent());
        $this->assertFalse(ChatMemberStatus::Restricted->hasLeft());
    }
}
