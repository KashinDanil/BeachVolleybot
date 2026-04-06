<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\TimeExtractor;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Telegram\MessageBuilders\DefaultTelegramMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class JoinWithTimeProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $from = $message->from;
        $time = TimeExtractor::extract($message->text ?? '');

        if (null === $time) {
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

        new PlayerRepository($db)->upsert($from->id, $from->firstName, $from->lastName, $from->username);

        $gamePlayerRepo = new GamePlayerRepository($db);

        if (!$gamePlayerRepo->updateTime($gameId, $from->id, $time)) {
            $gamePlayerRepo->create($gameId, $from->id, $time);

            $slotRepo = new GameSlotRepository($db);
            $slotRepo->create($gameId, $from->id, $slotRepo->getNextPosition($gameId));
        }

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
