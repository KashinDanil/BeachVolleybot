<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use BeachVolleybot\Game\GameBuilder;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final readonly class ArticleBuilder implements ArticleBuilderInterface
{
    private const string ARTICLE_TITLE = '🏐 New game';
    private const string ARTICLE_DESCRIPTION = 'Tap to create a new game';

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
            description: self::ARTICLE_DESCRIPTION,
            inputMessageContent: $message->getText(),
            inlineKeyboardMarkup: $message->getKeyboard(),
        );
    }

    private function buildGame(): GameInterface
    {
        return GameBuilder::buildFromNewGameData(
            NewGameData::fromUser(
                $this->inlineQuery->from,
                $this->inlineQuery->query,
                $this->inlineQuery->id,
            ),
        );
    }
}
