<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\NewGameSendProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NewGameConfirmMessageBuilder;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

final class NewGameSendProcessorTest extends ProcessorTestCase
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
        $this->runProcessor($this->dmSendUpdate('Bogatell'));

        $gameId = new GameManager()->resolveGameIdByChatMessage(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $this->assertNotNull($gameId);
        $this->assertStringContainsString('Bogatell', new GameRepository($this->db)->findById($gameId)['title']);
    }

    public function testTitleIsDateFirstSoVenueNameDoesNotShadowTheDate(): void
    {
        // "Gavà Mar" contains "Mar" (March); a venue-first title makes DateExtractor read it
        // as a March date. Date-first keeps the picked day winning the leftmost match.
        $this->runProcessor($this->dmSendUpdate('Gavà Mar'));

        $gameId = new GameManager()->resolveGameIdByChatMessage(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $title = new GameRepository($this->db)->findById($gameId)['title'];

        $resolved = GameDateResolver::resolve($title, new DateTimeImmutable());
        $this->assertNotNull($resolved);
        $this->assertSame('31.12', $resolved->format('d.m'));
    }

    public function testSkipCreatesGameWithNoLocation(): void
    {
        $this->runProcessor($this->dmSendUpdate(null));

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

        $this->runProcessor($this->dmSendUpdate('Bogatell'));

        $this->assertSame(0, new GameRepository($this->db)->countAll());
    }

    public function testRestartsTheWizardWhenTheKickoffDayHasAlreadyPassed(): void
    {
        // The wizard can sit on the confirm page for days; by the time Send is tapped
        // the chosen date may be in the past, so no game must be created and the
        // wizard rewinds to step 1 instead.
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::Send)->toJson(),
            $this->staleWizardText(),
        );

        $this->runProcessor($update);

        $this->assertSame(0, new GameRepository($this->db)->countAll());
        $this->assertSame(0, $this->sendMessageCount());

        $text = $this->editedText();
        $this->assertNotNull($text, 'Expected the wizard to rewind to the date picker');
        $this->assertStringContainsString('Step 1 of 4', $text);
        $this->assertAnsweredWith(CallbackAnswer::DATE_ALREADY_PASSED);
    }

    // Telegram delivers no callback for a tap on an old ephemeral message, so this path
    // cannot be reached today; it is kept for if that changes.
    public function testRestartsTheEphemeralWizardWhenTheKickoffDayHasAlreadyPassed(): void
    {
        $update = $this->groupEphemeralSendUpdate('Bogatell', $this->staleWizardText());

        $this->runProcessor($update);

        $this->assertSame(0, new GameRepository($this->db)->countAll());

        $text = $this->editedText();
        $this->assertNotNull($text, 'Expected the ephemeral wizard to rewind to the date picker');
        $this->assertStringContainsString('Step 1 of 4', $text);
    }

    public function testRejectsAnUnknownVenue(): void
    {
        // The confirm page can sit open for a while; if the venue catalog changes before
        // Send is tapped, the venue name round-tripped on the button must be re-validated
        // — this must reject rather than silently posting the game with no location.
        // The date uses an absolute year (unlike the wizard's own year-less rendering)
        // so the kickoff can never itself be the reason this test passes or fails —
        // only the venue check can.
        $update = $this->dmCallbackUpdate(
            NewGameCallbackData::create(NewGameCallbackAction::Send)->withVenueName('Atlantis')->toJson(),
            "🏐 New game — Step 4 of 4\n\n📅 31.12.2099\n🕒 " . self::PICKED_TIME . "\n📍 Atlantis",
        );

        $this->runProcessor($update);

        $this->assertSame(0, new GameRepository($this->db)->countAll());
        $this->assertSame(0, $this->sendMessageCount());
    }

    public function testReprocessingIsANoOp(): void
    {
        $update = $this->dmSendUpdate('Bogatell');

        $this->runProcessor($update);
        $this->runProcessor($update); // the second final tap finds the existing game and bails

        $this->assertSame(1, new GameRepository($this->db)->countAll());
        // First run posts the game and its DM share reply (2 sends); the second run bails before posting.
        $this->assertSame(2, $this->sendMessageCount());
    }

    public function testGroupCreatesGamePinsTheMessageAndEnqueuesWeather(): void
    {
        $this->runProcessor($this->groupEphemeralSendUpdate('Bogatell'));

        $this->assertNotNull(new GameManager()->resolveGameIdByChatMessage(self::GROUP_CHAT_ID, self::SENT_MESSAGE_ID));
        $this->assertTrue($this->calledApi('pinChatMessage'), 'Expected the posted message to be pinned in a group');
        $this->assertTrue($this->editedEphemeralMessage(), 'Expected the ephemeral wizard message to be edited to the success view');
    }

    public function testFollowsTheGameWithAShareReplyInDirectMessages(): void
    {
        $this->runProcessor($this->dmSendUpdate('Bogatell'));

        $gameId = new GameManager()->resolveGameIdByChatMessage(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $shareReply = $this->shareReplyTo(self::DM_CHAT_ID, self::SENT_MESSAGE_ID);
        $this->assertNotNull($shareReply, 'Expected a share reply to follow the posted game in a DM');

        $keyboard = json_decode($shareReply['args'][5]->toJson(), true)['inline_keyboard'];
        $this->assertSame("Forward game $gameId", $keyboard[0][0]['switch_inline_query']);
        $this->assertSame('Share', $keyboard[0][0]['text']);
    }

    public function testDoesNotSendAShareReplyInGroups(): void
    {
        $this->runProcessor($this->groupEphemeralSendUpdate('Bogatell'));

        $this->assertNull($this->shareReplyTo(self::GROUP_CHAT_ID, self::SENT_MESSAGE_ID));
    }

    private function runProcessor(TelegramUpdate $update): void
    {
        $callbackData = NewGameCallbackData::fromJson($update->callbackQuery->data);
        new NewGameSendProcessor($this->telegramSender, $callbackData)->process($update);
    }

    private function dmSendUpdate(?string $venueName): TelegramUpdate
    {
        $callbackData = NewGameCallbackData::create(NewGameCallbackAction::Send);
        if (null !== $venueName) {
            $callbackData = $callbackData->withVenueName($venueName);
        }

        return $this->dmCallbackUpdate($callbackData->toJson(), $this->wizardText($venueName));
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
                    'text' => $text ?? $this->wizardText('Bogatell'),
                ],
                'data' => $data,
            ],
        ]);
    }

    private function groupEphemeralSendUpdate(string $venueName, ?string $text = null): TelegramUpdate
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
                    'text' => $text ?? $this->wizardText($venueName),
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::Send)->withVenueName($venueName)->toJson(),
            ],
        ]);
    }

    // The exact confirm-page text the wizard renders (weekday, dd.mm — no year), with the
    // MarkdownV2 escaping stripped, as Telegram echoes it back in the callback.
    private function wizardText(?string $venueName): string
    {
        $message = new NewGameConfirmMessageBuilder(new Translator())
            ->build(new DateTimeImmutable(self::PICKED_DATE), self::PICKED_TIME, $venueName);

        return str_replace('\\', '', $message->getText()->getMessageText());
    }

    // A confirm-step text whose kickoff day is unambiguously in the past (absolute date,
    // per the fixture-date rule), as if the wizard had been left open past the picked day.
    private function staleWizardText(): string
    {
        return "🏐 New game — Step 4 of 4\n\n📅 01.01.2020\n🕒 " . self::PICKED_TIME . "\n📍 Bogatell";
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
