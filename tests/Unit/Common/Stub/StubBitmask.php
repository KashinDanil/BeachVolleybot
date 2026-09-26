<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common\Stub;

use BeachVolleybot\Common\AbstractBitmask;

final readonly class StubBitmask extends AbstractBitmask
{
    public function has(StubBitFlag $flag): bool
    {
        return $this->hasBit($flag);
    }

    public function with(StubBitFlag $flag): self
    {
        return $this->withBit($flag);
    }

    public function without(StubBitFlag $flag): self
    {
        return $this->withoutBit($flag);
    }
}
