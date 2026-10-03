<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common\Stub;

use BeachVolleybot\Common\BitFlag;

enum StubBitFlag: int implements BitFlag
{
    case First = 1;
    case Second = 2;

    public function bit(): int
    {
        return 1 << $this->value;
    }
}
