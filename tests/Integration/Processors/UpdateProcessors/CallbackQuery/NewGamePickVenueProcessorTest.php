<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGamePickVenueProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameLocationPickerMessageBuilder;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

final class NewGamePickVenueProcessorTest extends ProcessorTestCase
{
    private const int DM_CHAT_ID = 200;          // DM: chat id == user id
    private const int GROUP_CHAT_ID = -100;
    private const int WIZARD_MESSAGE_ID = 900;
    private const int EPHEMERAL_MESSAGE_ID = 901;
    private const int SENT_MESSAGE_ID = 42;      // BotApiStub::sendMessage returns 42
    private const string PICKED_DATE = '2099-12-31';
    private const string PICKED_TIME = '18:30';

    protected function setUp(): void
    {
        parent::setUp();
        // Pinning writes to pinned_messages, which lives in a migration the base case skips.
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../../../migrations/002_create_pinned_messages.sql'));
    }

    public function testCreatesGameAndPostsMessage(): void
    {
        $this->runProcessor($this->dmPickVenueUpdate('Bogatell'));

        $gameId = new GameManager()->resolveGameIdByChatMessage(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $this->assertNotNull($gameId);
        $this->assertStringContainsString('Bogatell', new GameRepository($this->db)->findById($gameId)['title']);
    }

    public function testTitleIsDateFirstSoVenueNameDoesNotShadowTheDate(): void
    {
        // "Gavà Mar" contains "Mar" (March); a venue-first title makes DateExtractor read it
        // as a March date. Date-first keeps the picked day winning the leftmost match.
        $this->runProcessor($this->dmPickVenueUpdate('Gavà Mar'));

        $gameId = new GameManager()->resolveGameIdByChatMessage(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $title = new GameRepository($this->db)->findById($gameId)['title'];

        $resolved = GameDateResolver::resolve($title, new DateTimeImmutable());
        $this->assertNotNull($resolved);
        $this->assertSame('31.12', $resolved->format('d.m'));
    }

    public function testSkipCreatesGameWithNoLocation(): void
    {
        $this->runProcessor($this->dmCallbackUpdate(NewGameCallbackData::create(NewGameCallbackAction::SkipVenue)->toJson()));

        $gameId = new GameManager()->resolveGameIdByChatMessage(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $this->assertNotNull($gameId);

        $title = new GameRepository($this->db)->findById($gameId)['title'];
        $this->assertNull(KnownVenues::findInTitle($title));
        $this->assertSame('31.12', GameDateResolver::resolve($title, new DateTimeImmutable())->format('d.m'));
        $this->assertStringContainsString(self::PICKED_TIME, $title);
    }

    public function testFailedMessagePostLeavesNoGame(): void
    {
        $this->bot->failSend = true;

        $this->runProcessor($this->dmPickVenueUpdate('Bogatell'));

        $this->assertSame(0, new GameRepository($this->db)->countAll());
    }

    public function testRejectsAKickoffWhoseDayHasAlreadyPassed(): void
    {
        // The wizard can sit on the location step for days; by the time the location is
        // picked the chosen date may be in the past, so no game must be created.
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::PickVenue)->withVenueName('Bogatell')->toJson(),
            $this->staleWizardText(),
        );

        $this->runProcessor($update);

        $this->assertSame(0, new GameRepository($this->db)->countAll());
        $this->assertSame(0, $this->sendMessageCount());
    }

    public function testReprocessingIsANoOp(): void
    {
        $update = $this->dmPickVenueUpdate('Bogatell');

        $this->runProcessor($update);
        $this->runProcessor($update); // the second final tap finds the existing game and bails

        $this->assertSame(1, new GameRepository($this->db)->countAll());
        // First run posts the game and its DM share reply (2 sends); the second run bails before posting.
        $this->assertSame(2, $this->sendMessageCount());
    }

    public function testGroupCreatesGamePinsTheMessageAndEnqueuesWeather(): void
    {
        $this->runProcessor($this->groupEphemeralPickVenueUpdate('Bogatell'));

        $this->assertNotNull(new GameManager()->resolveGameIdByChatMessage(self::GROUP_CHAT_ID, self::SENT_MESSAGE_ID));
        $this->assertTrue($this->calledApi('pinChatMessage'), 'Expected the posted message to be pinned in a group');
        $this->assertTrue($this->editedEphemeralMessage(), 'Expected the ephemeral wizard message to be edited to the success view');
    }

    public function testFollowsTheGameWithAShareReplyInDirectMessages(): void
    {
        $this->runProcessor($this->dmPickVenueUpdate('Bogatell'));

        $gameId = new GameManager()->resolveGameIdByChatMessage(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $shareReply = $this->shareReplyTo(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $this->assertNotNull($shareReply, 'Expected a share reply to follow the posted game in a DM');

        $keyboard = json_decode($shareReply['args'][5]->toJson(), true)['inline_keyboard'];
        $this->assertSame("Forward game $gameId", $keyboard[0][0]['switch_inline_query']);
        $this->assertSame('Share', $keyboard[0][0]['text']);
    }

    public function testDoesNotSendAShareReplyInGroups(): void
    {
        $this->runProcessor($this->groupEphemeralPickVenueUpdate('Bogatell'));

        $this->assertNull($this->shareReplyTo(self::GROUP_CHAT_ID, self::SENT_MESSAGE_ID));
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
        return "🏐 New game — Step 3 of 3\n\n📅 01.01.2020\n🕒 " . self::PICKED_TIME . "\n📍 pick a location below 👇";
    }

    private function sendMessageCount(): int
    {
        return count(array_filter($this->bot->calls, static fn(array $call): bool => 'sendMessage' === $call['method']));
    }

    /**
     * The share reply is a sendMessage that replies to the posted game message.
     * A reply carries a non-null reply_to_message_id (arg 4), which the game
     * message send never does.
     *
     * @return array{method: string, args: list<mixed>}|null
     */
    private function shareReplyTo(int $chatId, int $gameMessageId): ?array
    {
        foreach ($this->bot->calls as $call) {
            if ('sendMessage' === $call['method']
                && $chatId === $call['args'][0]
                && $gameMessageId === ($call['args'][4] ?? null)
            ) {
                return $call;
            }
        }

        return null;
    }

    private function editedEphemeralMessage(): bool
    {
        return $this->calledApi('editEphemeralMessageText');
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
