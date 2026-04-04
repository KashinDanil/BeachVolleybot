<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Telegram\Incoming\TelegramUpdate;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

class InlineQueryProcessor extends AbstractUpdateProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $inlineQuery = $update->inlineQuery;
        $from = $inlineQuery->from;

        $visibleName = htmlspecialchars($from->firstName, ENT_QUOTES);
        $hiddenUserLink = '<a href="https://a.com#' . htmlspecialchars(json_encode(['iq' => $inlineQuery->id, 'p' => [['number' => 1, 'id' => $from->id]]]), ENT_QUOTES) . '">&#8203;</a>';

        $messageText = htmlspecialchars($inlineQuery->query, ENT_QUOTES)
            . "\n\n1. " . $visibleName
            . $hiddenUserLink;

        $keyboard = new InlineKeyboardMarkup([
            [
                ['text' => 'Sign Out', 'callback_data' => '/eg_-p'],
                ['text' => 'Sign Up', 'callback_data' => '/eg_+p'],
            ],
            [
                ['text' => '-🏐', 'callback_data' => '/eg_-b'],
                ['text' => '+🏐', 'callback_data' => '/eg_+b'],
            ],
            [
                ['text' => '-🕸️', 'callback_data' => '/eg_-n'],
                ['text' => '+🕸️', 'callback_data' => '/eg_+n'],
            ],
        ]);

        $article = new Article(
            id: 'new_game_' . $inlineQuery->id,
            title: 'Create new game',
            description: $inlineQuery->query,
            inputMessageContent: new Text($messageText, 'HTML'),
            inlineKeyboardMarkup: $keyboard,
        );

        $this->bot->answerInlineQuery($inlineQuery->id, [$article]);
    }
}