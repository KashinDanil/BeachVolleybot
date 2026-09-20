<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\NewGame\NewGameConfirmProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\PlayersPerNetSelection;
use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameConfirmMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use DanilKashin\Localization\Language;
use DateTimeImmutable;

final class NewGameConfirmProcessorTest extends ProcessorTestCase
{
    private const int DM_CHAT_ID = 200;          // DM: chat id == user id
    private const int GROUP_CHAT_ID = -100;
    private const int WIZARD_MESSAGE_ID = 900;
    private const int EPHEMERAL_MESSAGE_ID = 901;
    private const string PICKED_DATE = '2099-12-31';
    private const string PICKED_TIME = '18:30';
    private const string VENUE = 'Bogatell';

    // --- the two arrow behaviours ---

    public function testIncreaseWhileUnsetChangesOnlyTheButtonLabel(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::AdjustPlayersPerNet)->withPlayersPerNet(7)->toJson(),
            $this->confirmText(PlayersPerNetSelection::pending(6)),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringNotContainsString('👥', $text);

        $keyboard = $this->editedKeyboard();
        $this->assertNotNull($keyboard);
        $this->assertSame('👥 7', $keyboard[1][1]['text']);
    }

    public function testIncreaseWhileAppliedChangesTheTextRowAndStaysApplied(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::AdjustPlayersPerNet)->withPlayersPerNet(9)->toJson(),
            $this->confirmText(PlayersPerNetSelection::applied(8)),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('👥 9 spots per net', $text);

        $keyboard = $this->editedKeyboard();
        $this->assertSame('🗑', $keyboard[1][1]['text']);
    }

    // --- set / remove ---

    public function testTappingSetAddsTheRowAndFlipsTheMiddleButtonToRemove(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::SetPlayersPerNet)->withPlayersPerNet(8)->toJson(),
            $this->confirmText(PlayersPerNetSelection::pending(8)),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertStringContainsString('👥 8 spots per net', $text);

        $keyboard = $this->editedKeyboard();
        $this->assertSame('🗑', $keyboard[1][1]['text']);
    }

    public function testTappingRemoveClearsTheRowAndRestoresTheValueOnTheButton(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::RemovePlayersPerNet)->withPlayersPerNet(9)->toJson(),
            $this->confirmText(PlayersPerNetSelection::applied(9)),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringNotContainsString('👥', $text);

        $keyboard = $this->editedKeyboard();
        $this->assertSame('👥 9', $keyboard[1][1]['text']);
    }

    // --- language switch ---

    public function testSwitchingLanguageRedrawsTheConfirmPageInTheNewOne(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::SetLanguage)->withLanguage(Language::RU)->toJson(),
            $this->confirmText(PlayersPerNetSelection::pending(PlayersPerNetSelection::DEFAULT), Language::EN),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('шаг 4 из 4', $text);
        $this->assertStringContainsString('Четверг, 31.12', $text);
        $this->assertStringContainsString(self::PICKED_TIME, $text);
        $this->assertStringContainsString(self::VENUE, $text);
    }

    public function testSwitchingAwayFromATranslatedPageKeepsTheRunningSelection(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::SetLanguage)->withLanguage(Language::EN)->toJson(),
            $this->confirmText(PlayersPerNetSelection::pending(PlayersPerNetSelection::DEFAULT), Language::RU),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('Step 4 of 4', $text);
        $this->assertStringContainsString('Thursday, 31.12', $text);
        $this->assertStringContainsString(self::PICKED_TIME, $text);
    }

    public function testSwitchingLanguagePreservesAnAppliedPlayersPerNetLimitAndRerendersIt(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::SetLanguage)->withLanguage(Language::RU)->toJson(),
            $this->confirmText(PlayersPerNetSelection::applied(8), Language::EN),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('👥 8 человек на сетку', $text);
    }

    public function testSwitchingLanguageKeepsAPendingWorkingValueInsteadOfResettingToTheDefault(): void
    {
        // The language button carries the current working value on its callback, since a
        // pending (not yet applied) count never makes it into the text to be read back.
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::SetLanguage)->withLanguage(Language::RU)->withPlayersPerNet(10)->toJson(),
            $this->confirmText(PlayersPerNetSelection::pending(10), Language::EN),
        );

        $this->runProcessor($update);

        $keyboard = $this->editedKeyboard();
        $this->assertNotNull($keyboard);
        $this->assertSame('👥 10', $keyboard[1][1]['text']);
    }

    public function testSwitchingLanguageRerendersTheVenueReadBackFromTheText(): void
    {
        // The language button no longer carries the venue on its callback — this proves
        // the redraw still finds it, the same way it reads date and time back from the text.
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::SetLanguage)->withLanguage(Language::RU)->toJson(),
            $this->confirmText(PlayersPerNetSelection::pending(PlayersPerNetSelection::DEFAULT), Language::EN),
        );

        $this->runProcessor($update);

        $this->assertStringContainsString(self::VENUE, $this->editedText());
    }

    // --- shared step-processor behaviour ---

    public function testRestartsTheWizardWhenTheKickoffDayHasAlreadyPassed(): void
    {
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::AdjustPlayersPerNet)->withPlayersPerNet(7)->toJson(),
            $this->staleWizardText(),
        );

        $this->runProcessor($update);

        $text = $this->editedText();
        $this->assertNotNull($text, 'Expected the wizard to rewind to the date picker');
        $this->assertStringContainsString('Step 1 of 4', $text);
        $this->assertAnsweredWith(CallbackAnswer::DATE_ALREADY_PASSED);
    }

    public function testGroupEditsTheEphemeralWizardMessage(): void
    {
        $update = $this->groupEphemeralCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::AdjustPlayersPerNet)->withPlayersPerNet(7)->toJson(),
            $this->confirmText(PlayersPerNetSelection::pending(6)),
        );

        $this->runProcessor($update);

        $this->assertTrue($this->calledApi('editEphemeralMessageText'), 'Expected the ephemeral wizard message to be edited to the confirm page');
    }

    // --- helpers ---

    private function confirmText(PlayersPerNetSelection $selection, string $language = Language::EN): string
    {
        $message = new NewGameConfirmMessageBuilder(new Translator($language, tempnam(sys_get_temp_dir(), 'bvb_missing_')))
            ->build(new DateTimeImmutable(self::PICKED_DATE), self::PICKED_TIME, self::VENUE, $selection);

        return str_replace('\\', '', $message->getText()->getMessageText());
    }

    // A confirm-step text whose kickoff day is unambiguously in the past (absolute date,
    // per the fixture-date rule), as if the wizard had been left open past the picked day.
    private function staleWizardText(): string
    {
        return "🏐 New game — Step 4 of 4\n\n📅 01.01.2020\n🕒 " . self::PICKED_TIME . "\n📍 " . self::VENUE;
    }

    private function runProcessor(TelegramUpdate $update): void
    {
        $callbackData = NewGameCallbackData::fromJson($update->callbackQuery->data);
        new NewGameConfirmProcessor($this->telegramSender, $callbackData)->process($update);
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
                    'text' => $text ?? $this->confirmText(PlayersPerNetSelection::pending(6)),
                ],
                'data' => $data,
            ],
        ]);
    }

    private function groupEphemeralCallbackUpdate(string $data, ?string $text = null): TelegramUpdate
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
                    'text' => $text ?? $this->confirmText(PlayersPerNetSelection::pending(6)),
                ],
                'data' => $data,
            ],
        ]);
    }

    /** @return ?list<list<array{text: string, callback_data: string}>> */
    private function editedKeyboard(): ?array
    {
        foreach ($this->bot->calls as $call) {
            if ('editMessageText' === $call['method']) {
                return json_decode($call['args'][5]->toJson(), true)['inline_keyboard'];
            }

            if ('call' === $call['method'] && 'editEphemeralMessageText' === ($call['args'][0] ?? null)) {
                $replyMarkup = $call['args'][1]['reply_markup'] ?? null;

                return null !== $replyMarkup ? json_decode($replyMarkup, true)['inline_keyboard'] : null;
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
