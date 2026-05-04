<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\Messages\Outgoing;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameLabel;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
use TelegramBot\Api\Types\Inline\QueryResult\Article;

final readonly class ForwardGameArticleBuilder implements ArticleBuilderInterface
{
    private const string ARTICLE_TITLE_PREFIX = '🏐 Game';
    private const string ARTICLE_DESCRIPTION  = 'Tap to send this game';

    public function __construct(
        private TelegramInlineQuery $inlineQuery,
        private int $gameId,
        private string $gameTitle,
        private Translator $translator,
    ) {
    }

    public function build(): Article
    {
        $game = GameFactory::fromGameId($this->gameId);
        $message = $game->buildTelegramMessage();

        return new Article(
            id: $this->inlineQuery->id,
            title: $this->buildArticleTitle(),
            description: $this->translator->translate(self::ARTICLE_DESCRIPTION),
            inputMessageContent: $message->getText(),
            inlineKeyboardMarkup: $message->getKeyboard(),
        );
    }

    private function buildArticleTitle(): string
    {
        return $this->translator->translate(self::ARTICLE_TITLE_PREFIX) . ' ' . GameLabel::format($this->gameId, $this->gameTitle);
    }
}
