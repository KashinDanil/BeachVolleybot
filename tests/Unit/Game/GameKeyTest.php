<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameKey;
use PHPUnit\Framework\TestCase;

final class GameKeyTest extends TestCase
{
    public function testFromMessageDerivesPrefixedKey(): void
    {
        $this->assertSame('msg:-100:55', GameKey::fromMessage(-100, 55));
    }

    public function testSameMessageAlwaysYieldsTheSameKey(): void
    {
        $this->assertSame(GameKey::fromMessage(-100, 55), GameKey::fromMessage(-100, 55));
    }

    public function testDifferentMessagesYieldDifferentKeys(): void
    {
        $this->assertNotSame(GameKey::fromMessage(-100, 55), GameKey::fromMessage(-100, 56));
    }
}
