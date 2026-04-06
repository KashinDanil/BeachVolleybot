<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Telegram\MessageBuilders\DefaultTelegramMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SetLocationProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        if (null === $message->location) {
            return;
        }

        if (null === $message->replyToMessage) {
            return;
        }

        $inlineQueryId = $this->extractInlineQueryIdFromMetaButton($message->replyToMessage);

        if (null === $inlineQueryId) {
            return;
        }

        $db = Connection::get();
        $gameRow = new GameRepository($db)->findGameAndInlineMessageIdsByInlineQueryId($inlineQueryId);

        if (null === $gameRow) {
            return;
        }

        $gameId = (int) $gameRow['game_id'];
        $inlineMessageId = (string) $gameRow['inline_message_id'];

        $location = sprintf('%s,%s', $message->location->latitude, $message->location->longitude);
        new GameRepository($db)->updateLocation($gameId, $location);

        $this->bot->call('setMessageReaction', [
            'chat_id' => $message->chat->id,
            'message_id' => $message->messageId,
            'reaction' => json_encode([['type' => 'emoji', 'emoji' => '✅']]),
        ]);
        $this->bot->deleteMessage($message->chat->id, $message->messageId);
        new InlineMessageRefresher($this->bot)->refresh($inlineMessageId);
    }

    private function extractInlineQueryIdFromMetaButton(TelegramMessage $replyToMessage): ?string
    {
        $metaButtonCallbackData = $replyToMessage->replyMarkup['inline_keyboard'][0][0]['callback_data'] ?? null;

        if (null === $metaButtonCallbackData) {
            return null;
        }

        $decoded = json_decode($metaButtonCallbackData, true, 512, JSON_THROW_ON_ERROR);

        return $decoded[DefaultTelegramMessageBuilder::KEY_INLINE_QUERY_ID] ?? null;
    }
}
