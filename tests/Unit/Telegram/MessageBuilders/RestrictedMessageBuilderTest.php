<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\Admin\RestrictedMessageBuilder;
use PHPUnit\Framework\TestCase;

final class RestrictedMessageBuilderTest extends TestCase
{
    public function testShowsTheRestrictedHeaderInBold(): void
    {
        $message = new RestrictedMessageBuilder()->build();

        $this->assertSame('*Access restricted*', $message->getText()->getMessageText());
    }

    public function testRemovesEveryButton(): void
    {
        $message = new RestrictedMessageBuilder()->build();

        $this->assertSame([], json_decode($message->getKeyboard()->toJson(), true)['inline_keyboard']);
    }
}
