<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGamePickDateProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use DanilKashin\Localization\Language;

final class NewGamePickDateProcessorTest extends ProcessorTestCase
{
    private const int DM_CHAT_ID = 200;
    private const int WIZARD_MESSAGE_ID = 900;
    private const string FUTURE_DATE = '2099-12-31';
    private const string PAST_DATE = '2020-01-01';

    public function testAdvancesToTheTimePickerForAFutureDate(): void
    {
        $this->runProcessor(self::FUTURE_DATE);

        $text = $this->editedText();
        $this->assertNotNull($text, 'Expected the wizard to advance to the time picker');
        $this->assertStringContainsString('Step 2 of 4', $text);
    }

    public function testRestartsTheWizardForADateWhoseDayHasAlreadyPassed(): void
    {
        // A date button rendered on an earlier day can be tapped once its day is already gone.
        $this->runProcessor(self::PAST_DATE);

        $text = $this->editedText();
        $this->assertNotNull($text, 'Expected the wizard to rewind to the date picker');
        $this->assertStringContainsString('Step 1 of 4', $text);
        $this->assertAnsweredWith(CallbackAnswer::DATE_ALREADY_PASSED);
    }

    public function testTheNextStepIsRenderedInTheLanguageTheTappedButtonCarried(): void
    {
        $this->runProcessor(self::FUTURE_DATE, Language::RU);

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('шаг 2 из 4', $text);
        $this->assertStringContainsString('Четверг, 31.12', $text);
    }

    public function testCarriesForwardAnAlreadyAppliedLimitIntoTheTimeStep(): void
    {
        // Reachable only by navigating all the way back from a later step to re-pick the date.
        $this->runProcessor(self::FUTURE_DATE, Language::EN, "New game — Step 1 of 4\n\n👥 8 spots per net");

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('👥 8 spots per net', $text);
    }

    private function runProcessor(string $isoDate, string $language = Language::EN, ?string $text = null): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_ng',
                'from' => ['id' => self::DM_CHAT_ID, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => self::WIZARD_MESSAGE_ID,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => self::DM_CHAT_ID, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => $text ?? 'New game — Step 1 of 4',
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::PickDate)
                    ->withDate($isoDate)
                    ->withLanguage($language)
                    ->toJson(),
            ],
        ]);

        $callbackData = NewGameCallbackData::fromJson($update->callbackQuery->data);
        new NewGamePickDateProcessor($this->telegramSender, $callbackData)->process($update);
    }
}
