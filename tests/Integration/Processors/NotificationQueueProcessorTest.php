<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Notifications\NotificationQueuePayload;
use BeachVolleybot\Notifications\NotificationSender;
use BeachVolleybot\Processors\NotificationQueueProcessor;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Integration\Processors\Stub\BotApiStub;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use DanilKashin\FileQueue\Queue\QueueMessage;
use TelegramBot\Api\InvalidJsonException;
use TelegramBot\Api\Types\Message;
use Throwable;
use TypeError;

final class NotificationQueueProcessorTest extends ProcessorTestCase
{
    private const string UPCOMING_TITLE = 'Bogatell 31.12.2099 18:00';
    private const string PAST_TITLE     = 'Bogatell 01.01.2020 18:00';

    private int $gameId;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(BASE_LOG_DIR . '/app.log');
        $this->gameId = $this->createGame(self::UPCOMING_TITLE);
    }

    // --- recipient ---

    public function testSendsOnlyToThePayloadsUser(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::PromotedIntoGame);
        $this->joinGameWithOptIn(201, NotificationType::PromotedIntoGame);

        $this->processNotificationFor(200, NotificationType::PromotedIntoGame);

        $this->assertSame([200], $this->getSentChatIds());
    }

    public function testSkipsAUserWhoLeftTheGameBeforeTheWorkerRan(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::PromotedIntoGame);
        $this->createSlot($this->gameId, 200, 1);
        $queuedMessage = $this->buildQueueMessageFor(200, NotificationType::PromotedIntoGame);

        new GameManager()->leaveGame($this->gameId, 200);
        $this->createProcessor()->process($queuedMessage);

        $this->assertFalse(new GameUserManager()->isUserInGame($this->gameId, 200));
        $this->assertSame([], $this->getSentChatIds());
    }

    public function testSkipsAUserWhoNeverJoinedTheGame(): void
    {
        $this->createUser(200);
        $this->optIn(200, NotificationType::PromotedIntoGame);

        $this->processNotificationFor(200, NotificationType::PromotedIntoGame);

        $this->assertSame([], $this->getSentChatIds());
    }

    public function testSkipsAUserWhoDidNotOptInToThatType(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::BumpedFromGame);

        $this->processNotificationFor(200, NotificationType::PromotedIntoGame);

        $this->assertSame([], $this->getSentChatIds());
    }

    public function testSkipsAUserWhoseNotificationsAreUnset(): void
    {
        $this->createUser(200);
        $this->createGameUser($this->gameId, 200);

        $this->processNotificationFor(200, NotificationType::PromotedIntoGame);

        $this->assertSame([], $this->getSentChatIds());
    }

    public function testSkipsAnUnknownUser(): void
    {
        $this->assertTrue($this->processNotificationFor(999, NotificationType::BumpedFromGame));
        $this->assertSame([], $this->getSentChatIds());
    }

    public function testIgnoresOtherGamesTheUserIsIn(): void
    {
        $this->joinOtherGameWithOptIn(200, NotificationType::PromotedIntoGame);

        $this->processNotificationFor(200, NotificationType::PromotedIntoGame);

        $this->assertSame([], $this->getSentChatIds());
    }

    public function testAUserOptedInToSeveralTypesGetsEachOfThem(): void
    {
        $this->createGameUser($this->gameId, 200);
        $this->db->update(
            'users',
            ['notifications' => new NotificationSettings()
                ->enable(NotificationType::GameReachedMinimumPlayers)
                ->enable(NotificationType::PromotedIntoGame)
                ->toInt()],
            ['telegram_user_id' => 200],
        );

        $this->processNotificationFor(200, NotificationType::GameReachedMinimumPlayers);
        $this->processNotificationFor(200, NotificationType::PromotedIntoGame);
        $this->processNotificationFor(200, NotificationType::GameShortBeforeKickoff);

        $this->assertSame([200, 200], $this->getSentChatIds());
    }

    public function testReportsASentNotificationAsProcessed(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::PromotedIntoGame);

        $this->assertTrue($this->processNotificationFor(200, NotificationType::PromotedIntoGame));
    }

    // --- message ---

    public function testWritesInTheRecipientsOwnLanguage(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::GameShortBeforeKickoff, languageCode: 'ru');
        $this->joinGameWithOptIn(201, NotificationType::GameShortBeforeKickoff, languageCode: 'es');
        $this->joinGameWithOptIn(202, NotificationType::GameShortBeforeKickoff);

        foreach ([200, 201, 202] as $userId) {
            $this->processNotificationFor($userId, NotificationType::GameShortBeforeKickoff);
        }

        $this->assertStringContainsString('Не хватает игроков', $this->getTextSentTo(200));
        $this->assertStringContainsString('aún faltan jugadores', $this->getTextSentTo(201));
        $this->assertStringContainsString("there still aren't enough players", $this->getTextSentTo(202));
    }

    public function testQuotesTheGameTitleAndSpellsTheKickoff(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::PromotedIntoGame);

        $this->processNotificationFor(200, NotificationType::PromotedIntoGame);

        $text = $this->getTextSentTo(200);
        $this->assertStringContainsString('on Thursday, 31 Dec 2099 at 18:00', $text);
        $this->assertStringContainsString('>Bogatell 31\.12\.2099 18:00', $text);
    }

    /** Clocks go back on 25.10.2099; the DM still reads the venue's 18:00, not the stored UTC hour. */
    public function testTimeChangedSpellsTheNewKickoffOnTheVenueClockAfterTheClockChange(): void
    {
        $this->retitleGame($this->gameId, 'Bogatell 25.10.2099 18:00');
        $this->joinGameWithOptIn(200, NotificationType::KickoffTimeChanged);

        $this->processNotificationFor(200, NotificationType::KickoffTimeChanged);

        $text = $this->getTextSentTo(200);
        $this->assertStringContainsString('on Sunday, 25 Oct 2099 at 18:00', $text);
        $this->assertStringContainsString('To change your own time in the game', $text);
    }

    public function testTimeChangedSpellsASummerKickoffOnTheVenueClock(): void
    {
        $this->retitleGame($this->gameId, 'Bogatell 24.10.2099 18:00');
        $this->joinGameWithOptIn(200, NotificationType::KickoffTimeChanged);

        $this->processNotificationFor(200, NotificationType::KickoffTimeChanged);

        $this->assertStringContainsString('on Saturday, 24 Oct 2099 at 18:00', $this->getTextSentTo(200));
    }

    // --- dropped ---

    public function testDropsANotificationForAGameThatAlreadyKickedOff(): void
    {
        $pastGameId = $this->createGame(self::PAST_TITLE, inlineMessageId: 'msg_past', gameKey: 'query_past');
        $this->createGameUser($pastGameId, 200);
        $this->optIn(200, NotificationType::GameShortBeforeKickoff);

        $this->assertTrue($this->processNotificationFor(200, NotificationType::GameShortBeforeKickoff, $pastGameId));
        $this->assertSame([], $this->getSentChatIds());
    }

    public function testDropsANotificationForADeletedGame(): void
    {
        $this->assertTrue($this->processNotificationFor(200, NotificationType::GameShortBeforeKickoff, 999));
        $this->assertSame([], $this->getSentChatIds());
    }

    public function testDropsAnUnrecognisedPayload(): void
    {
        $this->assertTrue($this->createProcessor()->process(new QueueMessage(['type' => '3', 'game_id' => $this->gameId])));
        $this->assertSame([], $this->getSentChatIds());
        $this->assertStringContainsString('unrecognised payload', file_get_contents(BASE_LOG_DIR . '/app.log'));
    }

    // --- delivery failures ---

    public function testLogsAFailedSend(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::GameReachedMinimumPlayers);
        $this->bot->failSend = true;

        $this->assertTrue($this->processNotificationFor(200, NotificationType::GameReachedMinimumPlayers));
        $this->assertStringContainsString(
            "Notification GameReachedMinimumPlayers for game #$this->gameId not delivered to user 200: see the sendMessage failure above",
            file_get_contents(BASE_LOG_DIR . '/app.log'),
        );
    }

    public function testLogsATelegramErrorAsNotDelivered(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::GameReachedMinimumPlayers);
        $this->failSendingTo(200, new InvalidJsonException('Unexpected response'));

        $this->assertTrue($this->processNotificationFor(200, NotificationType::GameReachedMinimumPlayers));
        $this->assertStringContainsString('not delivered to user 200: Unexpected response', file_get_contents(BASE_LOG_DIR . '/app.log'));
    }

    public function testLogsAnyOtherFailureAsNotDelivered(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::GameShortBeforeKickoff);
        $this->failSendingTo(200, new TypeError('Not a Telegram error'));

        $this->assertTrue($this->processNotificationFor(200, NotificationType::GameShortBeforeKickoff));
        $this->assertStringContainsString('not delivered to user 200: Not a Telegram error', file_get_contents(BASE_LOG_DIR . '/app.log'));
    }

    public function testOneRecipientsFailureDoesNotAffectTheNextOne(): void
    {
        $this->joinGameWithOptIn(200, NotificationType::GameShortBeforeKickoff);
        $this->joinGameWithOptIn(201, NotificationType::GameShortBeforeKickoff);
        $this->failSendingTo(200, new TypeError('Not a Telegram error'));

        $this->processNotificationFor(200, NotificationType::GameShortBeforeKickoff);
        $this->processNotificationFor(201, NotificationType::GameShortBeforeKickoff);

        $this->assertSame([200, 201], $this->getSentChatIds());
    }

    private function joinGameWithOptIn(int $userId, NotificationType $type, ?string $languageCode = null): void
    {
        $this->createUser($userId, languageCode: $languageCode);
        $this->createGameUser($this->gameId, $userId);
        $this->optIn($userId, $type);
    }

    private function joinOtherGameWithOptIn(int $userId, NotificationType $type): void
    {
        $otherGameId = $this->createGame(self::UPCOMING_TITLE, inlineMessageId: 'msg_other', gameKey: 'query_other');
        $this->createGameUser($otherGameId, $userId);
        $this->optIn($userId, $type);
    }

    private function optIn(int $userId, NotificationType $type): void
    {
        $this->db->update(
            'users',
            ['notifications' => new NotificationSettings()->enable($type)->toInt()],
            ['telegram_user_id' => $userId],
        );
    }

    private function failSendingTo(int $chatId, Throwable $failure): void
    {
        $this->bot = new class($chatId, $failure) extends BotApiStub {
            public function __construct(
                private readonly int $failingChatId,
                private readonly Throwable $failure,
            ) {
                parent::__construct();
            }

            public function sendMessage(
                $chatId,
                $text,
                $parseMode = null,
                $disablePreview = false,
                $replyToMessageId = null,
                $replyMarkup = null,
                $disableNotification = false,
                $messageThreadId = null,
                $protectContent = null,
                $allowSendingWithoutReply = null
            ): Message {
                $message = parent::sendMessage(...func_get_args());

                if ($this->failingChatId === $chatId) {
                    throw $this->failure;
                }

                return $message;
            }
        };
        $this->telegramSender = new TelegramMessageSender($this->bot);
    }

    private function processNotificationFor(int $userId, NotificationType $type, ?int $gameId = null): bool
    {
        return $this->createProcessor()->process($this->buildQueueMessageFor($userId, $type, $gameId));
    }

    private function buildQueueMessageFor(int $userId, NotificationType $type, ?int $gameId = null): QueueMessage
    {
        $notificationPayload = new NotificationQueuePayload($type, $gameId ?? $this->gameId, $userId);

        return new QueueMessage(json_decode(json_encode($notificationPayload), true));
    }

    private function createProcessor(): NotificationQueueProcessor
    {
        return new NotificationQueueProcessor(new NotificationSender($this->telegramSender));
    }

    /** @return list<int> */
    private function getSentChatIds(): array
    {
        return array_map(static fn(array $sendMessageCall): int => $sendMessageCall['args'][0], $this->getSendMessageCalls());
    }

    private function getTextSentTo(int $chatId): string
    {
        foreach ($this->getSendMessageCalls() as $sendMessageCall) {
            if ($chatId === $sendMessageCall['args'][0]) {
                return $sendMessageCall['args'][1];
            }
        }

        $this->fail("No message was sent to $chatId");
    }

    /** @return list<array{method: string, args: list<mixed>}> */
    private function getSendMessageCalls(): array
    {
        return array_values(array_filter($this->bot->calls, static fn(array $call): bool => 'sendMessage' === $call['method']));
    }
}
