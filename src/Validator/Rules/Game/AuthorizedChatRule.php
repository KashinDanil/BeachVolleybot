<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules\Game;

use BeachVolleybot\Database\AuthorizedChatRepository;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramChat;
use BeachVolleybot\Validator\Rules\RuleInterface;

readonly class AuthorizedChatRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'The bot is not authorized in this group';

    public function __construct(private TelegramChat $chat)
    {
    }

    // Convenience for the common single-rule gate; the rule stays usable inside a Validator too.
    public static function isSatisfiedBy(TelegramChat $chat): bool
    {
        return new self($chat)->isValid();
    }

    public function isValid(): bool
    {
        if (!$this->chat->isGroupChat()) {
            return true;
        }

        return new AuthorizedChatRepository(Connection::get())->isAuthorized($this->chat->id);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['chatId' => $this->chat->id]);
    }
}
