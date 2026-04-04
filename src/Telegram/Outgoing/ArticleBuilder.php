<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Outgoing;

use BeachVolleybot\Game\GameBuilder;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Incoming\TelegramInlineQuery;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final readonly class ArticleBuilder
{
    private const string ARTICLE_TITLE = 'Create new game';

    public function __construct(private TelegramInlineQuery $inlineQuery)
    {
    }

    public function build(): Article
    {
        $game = $this->buildGame();
        $message = $game->buildTelegramMessage();

        return new Article(
            id: $this->inlineQuery->id, //Important: must be the same as the inline query id to identify replies by this id
            title: self::ARTICLE_TITLE,
            description: $this->inlineQuery->query,
            inputMessageContent: $message->getText(),
            inlineKeyboardMarkup: $message->getKeyboard(),
        );
    }

    private function buildGame(): GameInterface
    {
        $from = $this->inlineQuery->from;
        $userId = $from->id;

        return new GameBuilder(
            gameRow: [
                'game_id' => 0,
                'inline_query_id' => $this->inlineQuery->id,
                'inline_message_id' => '',
                'title' => $this->inlineQuery->query,
            ],
            slotRows: [
                ['game_id' => 0, 'telegram_user_id' => $userId, 'position' => 1],
            ],
            gamePlayerRows: [
                ['game_id' => 0, 'telegram_user_id' => $userId, 'volleyball' => 1, 'net' => 1, 'time' => null],
            ],
            playerRows: [
                [
                    'telegram_user_id' => $userId,
                    'first_name' => $from->firstName,
                    'last_name' => $from->lastName,
                    'username' => $from->username,
                ],
            ],
        )->build();
    }
}
