<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

enum EquipmentResult: string
{
    case Added = 'added';
    case Removed = 'removed';
    case NotJoined = 'not_joined';
    case NoneLeft = 'none_left';
    case Error = 'error';
}
