<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\BotMention;
use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameKey;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameMessagePinner;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Game\NewGameFactory;
use BeachVolleybot\Game\ShareGameReplySender;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use BeachVolleybot\Validator\Validator;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use DateTimeImmutable;

class CreateGameFromMessageProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $chatId = $message->chat->id;
        $title = BotMention::strip($message->text ?? '');

        $sentAt = new DateTimeImmutable('@' . $message->date);
        if (!$this->isKickoffInFuture($title, $sentAt)) {
            return;
        }

        $gameKey = GameKey::fromMessage($chatId, $message->messageId);
        $gameManager = new GameManager();

        if (null !== $gameManager->resolveGameIdByGameKey($gameKey)) {
            return;
        }

        $newGameData = NewGameData::fromUser($message->from, $title, $gameKey);

        $sentMessageId = $this->telegramSender->sendMessage(
            $chatId,
            NewGameFactory::create($newGameData)->buildTelegramMessage(),
            $message->resolveMessageThreadId(),
        );

        if (0 === $sentMessageId) {
            Logger::logApp('Failed to send game message to chat ' . $chatId);

            return;
        }

        $gameId = $gameManager->createGame($newGameData);
        $gameManager->addChatMessage($gameId, $chatId, $sentMessageId);

        //Delete the original user message
        $this->telegramSender->deleteMessage($chatId, $message->messageId);

        if ($message->chat->isGroupChat()) {
            new GameMessagePinner($this->telegramSender)->pinGameMessage($chatId, $sentMessageId, $title, $message->date);
        }

        new ShareGameReplySender($this->telegramSender)->sendInDm($message->chat, $sentMessageId, $gameId, $message->from);

        new WeatherEnqueuer()->enqueue($gameId);
        $this->logUserAction($message->from, 'create_game_from_message', "gameId=$gameId");
    }

    private function isKickoffInFuture(string $title, DateTimeImmutable $reference): bool
    {
        $validationState = new Validator([
            new KickoffDayInTheFutureRule($title, $reference),
        ])->validate();

        return $validationState->isSuccess();
    }
}
