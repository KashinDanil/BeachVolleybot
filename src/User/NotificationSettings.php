<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Common\AbstractBitmask;

final readonly class NotificationSettings extends AbstractBitmask
{
    public function isEnabled(NotificationType $type): bool
    {
        return $this->hasBit($type);
    }

    public function enable(NotificationType $type): self
    {
        return $this->withBit($type);
    }

    public function disable(NotificationType $type): self
    {
        return $this->withoutBit($type);
    }
}
