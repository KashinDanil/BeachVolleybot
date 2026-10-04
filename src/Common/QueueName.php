<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

enum QueueName: string
{
    case Game = 'game_';
    case NewGame = 'game_new_';
    case Dm = 'dm_';
    case Pin = 'pin_';
    case Weather = 'weather_';
    case Notification = 'notification_';

    public function forId(null | int | string $id): string
    {
        return $this->value . $id;
    }
}
