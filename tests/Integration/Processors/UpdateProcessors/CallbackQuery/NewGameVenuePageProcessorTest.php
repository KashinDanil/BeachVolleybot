<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGameVenuePageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameConfirmMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\PlayersPerNetSelection;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use DateTimeImmutable;

final class NewGameVenuePageProcessorTest extends ProcessorTestCase
{
    private const int DM_CHAT_ID = 200;          // DM: chat id == user id
    private const int WIZARD_MESSAGE_ID = 900;
    private const string PICKED_DATE = '2099-12-31';
    private const string PICKED_TIME = '18:30';
    private const string VENUE = 'Bogatell';

    public function testShowsTheVenueStep(): void
    {
        $this->runProcessor($this->dmCallbackUpdate(PlayersPerNetSelection::pending(PlayersPerNetSelection::DEFAULT)));

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('Step 3 of 4', $text);
    }

    public function testCarriesForwardAnAppliedLimitReadBackFromTheIncomingText(): void
    {
        // Tapping Back off the confirm page carries the applied limit only in the text it
        // is already showing (the 👥 row) — this must read it back from there and carry it
        // into the venue page's own text, or re-picking a venue would drop it.
        $this->runProcessor($this->dmCallbackUpdate(PlayersPerNetSelection::applied(8)));

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringContainsString('👥 8 players per net', $text);
    }

    public function testTheVenuePagesButtonsNeverCarryTheLimit(): void
    {
        $this->runProcessor($this->dmCallbackUpdate(PlayersPerNetSelection::applied(8)));

        $keyboard = $this->editedKeyboard();
        $this->assertNotNull($keyboard);

        foreach (array_merge(...$keyboard) as $button) {
            $this->assertNull(NewGameCallbackData::fromJson($button['callback_data'])->getPlayersPerNet(), "'{$button['text']}' unexpectedly carries the limit");
        }
    }

    public function testCarriesNoLimitWhenNoneWasApplied(): void
    {
        $this->runProcessor($this->dmCallbackUpdate(PlayersPerNetSelection::pending(PlayersPerNetSelection::DEFAULT)));

        $text = $this->editedText();
        $this->assertNotNull($text);
        $this->assertStringNotContainsString('👥', $text);
    }

    private function runProcessor(TelegramUpdate $update): void
    {
        $callbackData = NewGameCallbackData::fromJson($update->callbackQuery->data);
        new NewGameVenuePageProcessor($this->telegramSender, $callbackData)->process($update);
    }

    private function dmCallbackUpdate(PlayersPerNetSelection $selection): TelegramUpdate
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
                    'text' => $this->confirmText($selection),
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::ShowVenuePage)->toJson(),
            ],
        ]);
    }

    // The confirm page's own text (weekday, dd.mm — no year), as Telegram echoes it back on
    // the callback fired by tapping its Back button.
    private function confirmText(PlayersPerNetSelection $selection): string
    {
        $message = new NewGameConfirmMessageBuilder(new Translator())
            ->build(new DateTimeImmutable(self::PICKED_DATE), self::PICKED_TIME, self::VENUE, $selection);

        return str_replace('\\', '', $message->getText()->getMessageText());
    }

    private function editedKeyboard(): ?array
    {
        foreach ($this->bot->calls as $call) {
            if ('editMessageText' === $call['method']) {
                return json_decode($call['args'][5]->toJson(), true)['inline_keyboard'];
            }
        }

        return null;
    }
}
