<?php

declare(strict_types=1);

namespace BeachVolleybot\User;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameMessagePinner;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\UnauthorizedGameMessageBuilder;
use BeachVolleybot\Telegram\Messages\GameMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Validator\Rules\Game\AuthorizedChatRule;

final readonly class GameMessageAuthorizer
{
    public function __construct(
        private TelegramMessageSender $telegramSender,
    ) {
    }

    // null while undecided (row not written yet, or no inline query id); true authorized, false rejected.
    public function authorize(TelegramMessage $message): ?bool
    {
        $inlineQueryId = GameCallbackData::extractInlineQueryId($message);

        if (null === $inlineQueryId) {
            return null;
        }

        $gameManager = new GameManager();
        $gameMessage = $gameManager->findGameMessageByInlineQueryId($inlineQueryId);

        // Row isn't written yet (chosen_inline_result races us); a later edit will settle it.
        if (null === $gameMessage) {
            return null;
        }

        if (null !== $gameMessage->authorized) {
            return $gameMessage->authorized;
        }

        if (AuthorizedChatRule::isSatisfiedBy($message->chat)) {
            $gameManager->recordGameMessageAuthorizationByInlineQueryId($inlineQueryId, true);

            return true;
        }

        if ($this->maskMessage($gameMessage, $message->from)) {
            $this->unpinMessage($message);
            $gameManager->recordGameMessageAuthorizationByInlineQueryId($inlineQueryId, false);
        }

        return false;
    }

    private function maskMessage(GameMessage $gameMessage, TelegramUser $telegramUser): bool
    {
        $unauthorizedMessage = new UnauthorizedGameMessageBuilder(Translator::fromUser($telegramUser))->build();

        return $this->telegramSender->editGameMessage($gameMessage, $unauthorizedMessage);
    }

    private function unpinMessage(TelegramMessage $message): void
    {
        // The message may have been pinned while authorization was still undecided (the inline pin races the row write).
        new GameMessagePinner($this->telegramSender)->unpin($message->chat->id, $message->messageId);
    }
}
