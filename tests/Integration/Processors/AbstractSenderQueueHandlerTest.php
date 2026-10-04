<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Processors\AbstractSenderQueueHandler;
use BeachVolleybot\Processors\Handlers\PinHandlers\AbstractPinQueueHandler;
use BeachVolleybot\Processors\ProcessorRegistryFactory;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\DeletePinNotificationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;
use LogicException;

final class AbstractSenderQueueHandlerTest extends ProcessorTestCase
{
    private const int SENDER_ID = 555;

    public function testHandsTheEnsuredSenderToTheProcessor(): void
    {
        $sender = $this->senderHandedOver($this->privateMessagePayload('hi', fromId: self::SENDER_ID));

        $this->assertEquals(new UserManager()->findUserRecordById(self::SENDER_ID), $sender);
    }

    public function testDirectMessageCreatesAMissingSenderWithNotificationsOff(): void
    {
        $sender = $this->senderHandedOver($this->privateMessagePayload('hi', fromId: self::SENDER_ID));

        $this->assertSame(0, $sender->notifications?->toInt());
    }

    public function testDirectMessageTurnsUnsetNotificationsOff(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);

        $sender = $this->senderHandedOver($this->privateMessagePayload('hi', fromId: self::SENDER_ID));

        $this->assertSame(0, $sender->notifications?->toInt());
    }

    public function testDirectMessageKeepsStoredNotifications(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);
        $stored = new NotificationSettings()->enable(NotificationType::BumpedFromGame);
        $this->db->update('users', ['notifications' => $stored->toInt()], ['telegram_user_id' => self::SENDER_ID]);

        $sender = $this->senderHandedOver($this->privateMessagePayload('hi', fromId: self::SENDER_ID));

        $this->assertSame($stored->toInt(), $sender->notifications?->toInt());
    }

    public function testDirectCallbackQueryTurnsUnsetNotificationsOff(): void
    {
        $this->createUser(telegramUserId: self::SENDER_ID);

        $sender = $this->senderHandedOver(
            $this->adminCallbackQueryPayload('{"ua":"unl"}', fromId: self::SENDER_ID, chatId: self::SENDER_ID),
        );

        $this->assertSame(0, $sender->notifications?->toInt());
    }

    public function testGroupMessageCreatesTheSenderWithNotificationsUnset(): void
    {
        $sender = $this->senderHandedOver($this->replyMessagePayload('hi', 'query_1', fromId: self::SENDER_ID));

        $this->assertSame(self::SENDER_ID, $sender->telegramUserId);
        $this->assertNull($sender->notifications);
    }

    public function testPressOnAnInlineMessageLeavesNotificationsUnset(): void
    {
        $sender = $this->senderHandedOver($this->callbackQueryPayload('msg_1', '{"a":"j"}', fromId: self::SENDER_ID));

        $this->assertNull($sender->notifications);
    }

    public function testEnsuresTheSenderWithASingleQuery(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('hi', fromId: self::SENDER_ID));

        $queries = $this->queriesDuring(fn() => $this->senderHandler()->createProcessor($this->telegramSender, $update));

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO users', $queries[0]);
    }

    public function testUpdateWithoutASenderIsALogicError(): void
    {
        $update = TelegramUpdate::fromArray(['update_id' => 1]);

        $this->expectException(LogicException::class);

        $this->senderHandler()->createProcessor($this->telegramSender, $update);
    }

    public function testEveryQueuedHandlerButThePinHandlersEnsuresTheSender(): void
    {
        foreach (ProcessorRegistryFactory::queuedHandlers() as $handler) {
            $this->assertSame(
                !$handler instanceof AbstractPinQueueHandler,
                $handler instanceof AbstractSenderQueueHandler,
                $handler::class,
            );
        }
    }

    public function testThePinNotificationDoesNotStoreTheBot(): void
    {
        $update = TelegramUpdate::fromArray($this->pinNotificationPayload(-100, messageId: 2, pinnedMessageId: 1));

        $processor = ProcessorRegistryFactory::createQueued()->resolveProcessor($update, $this->telegramSender);

        $this->assertInstanceOf(DeletePinNotificationProcessor::class, $processor);
        $this->assertSame(0, new UserManager()->countUsers());
    }

    private function senderHandedOver(array $payload): UserRecord
    {
        return $this->senderHandler()->createProcessor($this->telegramSender, TelegramUpdate::fromArray($payload))->sender;
    }

    private function senderHandler(): AbstractSenderQueueHandler
    {
        return new readonly class extends AbstractSenderQueueHandler {
            public function matches(TelegramUpdate $update): bool
            {
                return true;
            }

            public function routeToQueue(TelegramUpdate $update): ?string
            {
                return null;
            }

            protected function createSenderProcessor(
                TelegramMessageSender $telegramSender,
                TelegramUpdate $update,
                UserRecord $sender,
            ): AbstractActionProcessor {
                return new class($telegramSender, $sender) extends AbstractActionProcessor {
                    public function __construct(
                        TelegramMessageSender $telegramSender,
                        public readonly UserRecord $sender,
                    ) {
                        parent::__construct($telegramSender);
                    }

                    public function process(TelegramUpdate $update): void
                    {
                    }
                };
            }
        };
    }
}
