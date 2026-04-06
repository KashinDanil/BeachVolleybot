<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

enum LeaveResult: string
{
    case Left = 'left';
    case NotJoined = 'not_joined';
}
