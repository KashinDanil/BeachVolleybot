<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGamePickVenueProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameLocationPickerMessageBuilder;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use DateTimeImmutable;

final class NewGamePickVenueProcessorTest extends ProcessorTestCase
{
    private const int DM_CHAT_ID = 200;          // DM: chat id == user id
    private const int GROUP_CHAT_ID = -100;
    private const int WIZARD_MESSAGE_ID = 900;
    private const int EPHEMERAL_MESSAGE_ID = 901;
    private const string PICKED_DATE = '2099-12-31';
    private const string PICKED_TIME = '18:30';

    public function testShowsTheConfirmPageWithThePickedVenue(): void
    {
        $this->runProcessor($this->dmPickVenueUpdate('Bogatell'));

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('Step 4 of 4', $text);
        $this->assertStringContainsString('Bogatell', $text);
        $this->assertSame(0, $this->sendMessageCount(), 'No game message must be posted before Send is pressed');
    }

    public function testShowsTheConfirmPageWithNoLocationLineWhenVenueIsSkipped(): void
    {
        $this->runProcessor($this->dmCallbackUpdate(NewGameCallbackData::create(NewGameCallbackAction::SkipVenue)->toJson()));

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('Step 4 of 4', $text);
        $this->assertStringNotContainsString('Skip location', $text);
        $this->assertStringNotContainsString('📍', $text);
    }

    public function testRejectsAKickoffWhoseDayHasAlreadyPassed(): void
    {
        // The wizard can sit on the location step for days; by the time the location is
        // picked the chosen date may be in the past, so the confirm page must not appear.
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::PickVenue)->withVenueName('Bogatell')->toJson(),
            $this->staleWizardText(),
        );

        $this->runProcessor($update);

        $this->assertNull($this->editedText());
    }

    public function testGroupEditsTheEphemeralWizardMessage(): void
    {
        $this->runProcessor($this->groupEphemeralPickVenueUpdate('Bogatell'));

        $this->assertTrue($this->calledApi('editEphemeralMessageText'), 'Expected the ephemeral wizard message to be edited to the confirm page');
    }

    private function runProcessor(TelegramUpdate $update): void
    {
        $callbackData = NewGameCallbackData::fromJson($update->callbackQuery->data);
        new NewGamePickVenueProcessor($this->telegramSender, $callbackData)->process($update);
    }

    private function dmPickVenueUpdate(string $venueName): TelegramUpdate
    {
        return $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::PickVenue)->withVenueName($venueName)->toJson(),
        );
    }

    private function dmCallbackUpdate(string $data, ?string $text = null): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
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
                    'text' => $text ?? $this->wizardText(),
                ],
                'data' => $data,
            ],
        ]);
    }

    private function groupEphemeralPickVenueUpdate(string $venueName): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_ng',
                'from' => ['id' => self::DM_CHAT_ID, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => 0,
                    'ephemeral_message_id' => self::EPHEMERAL_MESSAGE_ID,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => self::GROUP_CHAT_ID, 'type' => 'supergroup'],
                    'date' => 1700000000,
                    'text' => $this->wizardText(),
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::PickVenue)->withVenueName($venueName)->toJson(),
            ],
        ]);
    }

    // The exact location-picker text the wizard renders (weekday, dd.mm — no year), with the
    // MarkdownV2 escaping stripped, as Telegram echoes it back in the callback.
    private function wizardText(): string
    {
        $message = new NewGameLocationPickerMessageBuilder(new Translator())
            ->build(new DateTimeImmutable(self::PICKED_DATE), self::PICKED_TIME);

        return str_replace('\\', '', $message->getText()->getMessageText());
    }

    // A location-step text whose kickoff day is unambiguously in the past (absolute date,
    // per the fixture-date rule), as if the wizard had been left open past the picked day.
    private function staleWizardText(): string
    {
        return "🏐 New game — Step 3 of 4\n\n📅 01.01.2020\n🕒 " . self::PICKED_TIME . "\n📍 pick a location below 👇";
    }

    private function sendMessageCount(): int
    {
        return count(array_filter($this->bot->calls, static fn(array $call): bool => 'sendMessage' === $call['method']));
    }

    private function editedText(): ?string
    {
        foreach ($this->bot->calls as $call) {
            if ('editMessageText' === $call['method']) {
                return str_replace('\\', '', $call['args'][2]);
            }

            if ('call' === $call['method'] && 'editEphemeralMessageText' === ($call['args'][0] ?? null)) {
                return null === $call['args'][1]['text'] ? null : str_replace('\\', '', $call['args'][1]['text']);
            }
        }

        return null;
    }

    private function calledApi(string $method): bool
    {
        foreach ($this->bot->calls as $call) {
            if ('call' === $call['method'] && $method === ($call['args'][0] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
