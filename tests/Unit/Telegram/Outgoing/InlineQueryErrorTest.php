<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Outgoing;

use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Processors\UpdateProcessors\InlineQueryProcessor;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Integration\Processors\Stub\BotApiStub;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use PHPUnit\Framework\TestCase;

final class InlineQueryErrorTest extends TestCase
{
    public function testResolvesDateAndTimeNotFound(): void
    {
        $error = new ValidationError(DateTimeInTitleRule::ERROR_DATE_AND_TIME_MISSING);
        $inlineQueryError = InlineQueryError::fromError($error);

        $this->assertSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_TITLE, $inlineQueryError->title());
        $this->assertSame(InlineQueryError::DATE_AND_TIME_NOT_FOUND_DESCRIPTION, $inlineQueryError->description());
    }

    public function testResolvesDateNotFound(): void
    {
        $error = new ValidationError(DateTimeInTitleRule::ERROR_DATE_MISSING);
        $inlineQueryError = InlineQueryError::fromError($error);

        $this->assertSame(InlineQueryError::DATE_NOT_FOUND_TITLE, $inlineQueryError->title());
        $this->assertSame(InlineQueryError::DATE_NOT_FOUND_DESCRIPTION, $inlineQueryError->description());
    }

    public function testResolvesTimeNotFound(): void
    {
        $error = new ValidationError(DateTimeInTitleRule::ERROR_TIME_MISSING);
        $inlineQueryError = InlineQueryError::fromError($error);

        $this->assertSame(InlineQueryError::TIME_NOT_FOUND_TITLE, $inlineQueryError->title());
        $this->assertSame(InlineQueryError::TIME_NOT_FOUND_DESCRIPTION, $inlineQueryError->description());
    }

    public function testFallsBackToUnknownForUnmappedError(): void
    {
        $error = new ValidationError('Some unknown error');
        $inlineQueryError = InlineQueryError::fromError($error);

        $this->assertSame(InlineQueryError::UNKNOWN_TITLE, $inlineQueryError->title());
        $this->assertSame(InlineQueryError::UNKNOWN_DESCRIPTION, $inlineQueryError->description());
    }

    public function testAllProcessorValidationRulesAreCovered(): void
    {
        $processor = new InlineQueryProcessor(new TelegramMessageSender(new BotApiStub()));
        $rules = $processor->validationRules('');

        foreach ($rules as $rule) {
            $rule->isValid();
            $error = $rule->getError();
            $inlineQueryError = InlineQueryError::fromError($error);

            $this->assertNotSame(
                InlineQueryError::UNKNOWN_TITLE,
                $inlineQueryError->title(),
                sprintf('Validation rule %s error "%s" is not covered in InlineQueryError', $rule::class, $error->getMessage()),
            );
        }
    }
}
