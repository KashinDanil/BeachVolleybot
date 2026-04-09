<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Outgoing;

use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Outgoing\ErrorArticleBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use DanilKashin\Localization\Language;
use PHPUnit\Framework\TestCase;

final class ErrorArticleBuilderTest extends TestCase
{
    public function testArticleIdIsError(): void
    {
        $article = $this->buildArticle();

        $this->assertSame('error', $article->getId());
    }

    public function testTitleMatchesInlineQueryError(): void
    {
        $error = InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));
        $article = $this->buildArticle($error);

        $this->assertSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_TITLE, $article->getTitle());
    }

    public function testDescriptionMatchesInlineQueryError(): void
    {
        $error = InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));
        $article = $this->buildArticle($error);

        $this->assertSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_DESCRIPTION, $article->getDescription());
    }

    public function testUnknownErrorTitle(): void
    {
        $error = InlineQueryError::fromError(new ValidationError('Unknown error'));
        $article = $this->buildArticle($error);

        $this->assertSame(InlineQueryError::UNKNOWN_TITLE, $article->getTitle());
    }

    public function testUnknownErrorDescription(): void
    {
        $error = InlineQueryError::fromError(new ValidationError('Unknown error'));
        $article = $this->buildArticle($error);

        $this->assertSame(InlineQueryError::UNKNOWN_DESCRIPTION, $article->getDescription());
    }

    public function testArticleHasInputMessageContent(): void
    {
        $article = $this->buildArticle();

        $this->assertNotNull($article->getInputMessageContent());
    }

    public function testInputMessageContentContainsBotUsername(): void
    {
        $article = $this->buildArticle();

        $this->assertStringContainsString('@' . BOT_USERNAME, $article->getInputMessageContent()->getMessageText());
    }

    public function testTranslatesTitle(): void
    {
        $error = InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));
        $translator = new Translator(Language::RU);
        $article = (new ErrorArticleBuilder($error, $translator))->build();

        $this->assertNotSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_TITLE, $article->getTitle());
    }

    public function testTranslatesDescription(): void
    {
        $error = InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));
        $translator = new Translator(Language::RU);
        $article = (new ErrorArticleBuilder($error, $translator))->build();

        $this->assertNotSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_DESCRIPTION, $article->getDescription());
    }

    private function buildArticle(?InlineQueryError $error = null): \TelegramBot\Api\Types\Inline\QueryResult\Article
    {
        $error ??= InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));

        return (new ErrorArticleBuilder($error, new Translator()))->build();
    }
}
