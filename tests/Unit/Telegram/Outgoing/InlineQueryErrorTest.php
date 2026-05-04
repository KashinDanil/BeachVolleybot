<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\Outgoing;

use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Processors\UpdateProcessors\InlineQueryProcessor;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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

    public function testResolvesKickoffDayInThePast(): void
    {
        $error = new ValidationError(KickoffDayInTheFutureRule::ERROR_MESSAGE);
        $inlineQueryError = InlineQueryError::fromError($error);

        $this->assertSame(InlineQueryError::KICKOFF_DAY_IN_THE_PAST_TITLE, $inlineQueryError->title());
        $this->assertSame(InlineQueryError::KICKOFF_DAY_IN_THE_PAST_DESCRIPTION, $inlineQueryError->description());
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
        foreach (InlineQueryProcessor::newGameValidationRules('') as $rule) {
            foreach ($this->errorMessageConstantsOf($rule::class) as $constantName => $errorMessage) {
                $inlineQueryError = InlineQueryError::fromError(new ValidationError($errorMessage));

                $this->assertNotSame(
                    InlineQueryError::UNKNOWN_TITLE,
                    $inlineQueryError->title(),
                    sprintf('%s::%s ("%s") is not mapped in InlineQueryError::fromError', $rule::class, $constantName, $errorMessage),
                );
            }
        }
    }

    /** @return array<string, string> */
    private function errorMessageConstantsOf(string $ruleClass): array
    {
        $errorMessages = [];

        foreach (new ReflectionClass($ruleClass)->getConstants() as $name => $value) {
            if (str_starts_with($name, 'ERROR_') && is_string($value)) {
                $errorMessages[$name] = $value;
            }
        }

        return $errorMessages;
    }
}
