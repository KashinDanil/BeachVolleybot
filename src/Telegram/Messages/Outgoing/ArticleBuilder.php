<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use BeachVolleybot\Game\GameBuilder;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
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
        return GameBuilder::fromNewGameData(
            NewGameData::fromUser(
                $this->inlineQuery->from,
                $this->inlineQuery->query,
                $this->inlineQuery->id,
            ),
        );
    }
}
