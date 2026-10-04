<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Helpers;

use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\User\UserRecord;

final readonly class ProfileNameFormatter
{
    public function __construct(private MessageFormatterInterface $formatter)
    {
    }

    public function format(string $name, ?string $link): string
    {
        if (null === $link) {
            return $this->formatter->escape($name);
        }

        return $this->formatter->link($name, $link);
    }

    public function formatUser(UserRecord $user): string
    {
        return $this->format(
            Player::buildName($user->firstName, $user->lastName),
            Player::buildLink($user->username),
        );
    }
}
