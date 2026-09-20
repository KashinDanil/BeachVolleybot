<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Outgoing;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Outgoing\ErrorArticleBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use BeachVolleybot\Validator\Rules\DateTime\DateTimeInTitleRule;
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

    public function testPrivateChatSuggestsTheNewGameCommand(): void
    {
        $messageText = $this->buildArticle(isGroupChat: false)->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString($this->escapeCommand(Command::NewGame->value), $messageText);
    }

    public function testGroupChatSuggestsTypingTheCommandsMenuSlash(): void
    {
        $messageText = $this->buildArticle(isGroupChat: true)->getInputMessageContent()->getMessageText();

        $this->assertStringContainsString('`/`', $messageText);
        $this->assertStringNotContainsString(Command::NewGame->value, $messageText);
    }

    public function testTranslatesTitle(): void
    {
        $error = InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));
        $translator = new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
        $article = (new ErrorArticleBuilder($error, $translator, isGroupChat: false))->build();

        $this->assertNotSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_TITLE, $article->getTitle());
    }

    public function testTranslatesDescription(): void
    {
        $error = InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));
        $translator = new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
        $article = (new ErrorArticleBuilder($error, $translator, isGroupChat: false))->build();

        $this->assertNotSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_DESCRIPTION, $article->getDescription());
    }

    private function buildArticle(?InlineQueryError $error = null, bool $isGroupChat = false): \TelegramBot\Api\Types\Inline\QueryResult\Article
    {
        $error ??= InlineQueryError::fromError(new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING));

        return (new ErrorArticleBuilder($error, new Translator(), $isGroupChat))->build();
    }

    private function escapeCommand(string $command): string
    {
        return str_replace('_', '\\_', $command);
    }
}
