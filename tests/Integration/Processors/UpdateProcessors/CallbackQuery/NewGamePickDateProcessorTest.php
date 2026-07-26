<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGamePickDateProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class NewGamePickDateProcessorTest extends ProcessorTestCase
{
    private const int DM_CHAT_ID = 200;
    private const int WIZARD_MESSAGE_ID = 900;
    private const string FUTURE_DATE = '2099-12-31';
    private const string PAST_DATE = '2020-01-01';

    public function testAdvancesToTheTimePickerForAFutureDate(): void
    {
        $this->runProcessor(self::FUTURE_DATE);

        $this->assertTrue($this->editedTheWizard(), 'Expected the wizard to advance to the time picker');
    }

    public function testRejectsADateWhoseDayHasAlreadyPassed(): void
    {
        // A date button rendered on an earlier day can be tapped once its day is already gone.
        $this->runProcessor(self::PAST_DATE);

        $this->assertFalse($this->editedTheWizard(), 'A past date must not advance the wizard');
    }

    private function runProcessor(string $isoDate): void
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
                    'text' => 'New game — Step 1 of 3',
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::PickDate)->withDate($isoDate)->toJson(),
            ],
        ]);

        $callbackData = NewGameCallbackData::fromJson($update->callbackQuery->data);
        new NewGamePickDateProcessor($this->telegramSender, $callbackData)->process($update);
    }

    private function editedTheWizard(): bool
    {
        foreach ($this->bot->calls as $call) {
            if ('editMessageText' === $call['method']) {
                return true;
            }
        }

        return false;
    }
}
