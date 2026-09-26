<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\AuthorizedChatRepository;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\CurrentUser;

class BotMembershipProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $membership = $update->myChatMember;
        $repository = new AuthorizedChatRepository(Connection::get());

        if ($membership->botLeft()) {
            $repository->deauthorize($membership->chat->id);

            return;
        }

        if ($membership->botPresent() && CurrentUser::fromTelegramId($membership->from->id)->isRoot()) {
            $repository->authorize($membership->chat->id, $membership->from->id);
            Logger::logApp(sprintf('Authorized chat %d, added by root %d', $membership->chat->id, $membership->from->id));
        }
    }
}
