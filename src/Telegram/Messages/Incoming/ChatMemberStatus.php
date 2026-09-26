<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Incoming;

enum ChatMemberStatus: string
{
    case Creator = 'creator';
    case Administrator = 'administrator';
    case Member = 'member';
    case Restricted = 'restricted';
    case Left = 'left';
    case Kicked = 'kicked';

    public function isPresent(): bool
    {
        // Restricted is neither present nor gone: it is an ambiguous membership we don't act on.
        return match ($this) {
            self::Creator, self::Administrator, self::Member => true,
            self::Restricted, self::Left, self::Kicked => false,
        };
    }

    public function hasLeft(): bool
    {
        return match ($this) {
            self::Left, self::Kicked => true,
            self::Creator, self::Administrator, self::Member, self::Restricted => false,
        };
    }
}
