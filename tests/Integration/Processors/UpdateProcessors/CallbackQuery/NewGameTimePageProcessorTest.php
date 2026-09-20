<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGameTimePageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class NewGameTimePageProcessorTest extends ProcessorTestCase
{
    private const int DM_CHAT_ID = 200;
    private const int WIZARD_MESSAGE_ID = 900;

    public function testShowsTheTimeStep(): void
    {
        $this->runProcessor("🏐 New game — Step 3 of 4\n\n📅 31.12.2099\n🕒 18:30\n📍 pick a location below 👇");

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('Step 2 of 4', $text);
    }

    public function testCarriesForwardAnAlreadyAppliedLimit(): void
    {
        // Reached either from the location step's Back button or the time step's own
        // pagination — both must read the limit back from whatever text they were tapped from.
        $this->runProcessor("🏐 New game — Step 3 of 4\n\n📅 31.12.2099\n🕒 18:30\n📍 pick a location below 👇\n👥 8 spots per net");

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('👥 8 spots per net', $text);
    }

    public function testCarriesNoLimitWhenNoneWasApplied(): void
    {
        $this->runProcessor("🏐 New game — Step 3 of 4\n\n📅 31.12.2099\n🕒 18:30\n📍 pick a location below 👇");

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringNotContainsString('👥', $text);
    }

    private function runProcessor(string $text): void
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
                    'text' => $text,
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::ShowTimePage)->toJson(),
            ],
        ]);

        $callbackData = NewGameCallbackData::fromJson($update->callbackQuery->data);
        new NewGameTimePageProcessor($this->telegramSender, $callbackData)->process($update);
    }
}
