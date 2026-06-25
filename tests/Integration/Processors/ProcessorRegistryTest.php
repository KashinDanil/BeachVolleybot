<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCommandProcessor;
use BeachVolleybot\Processors\ProcessorRegistry;
use BeachVolleybot\Processors\ProcessorRegistryFactory;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CompositeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\DeletePinNotificationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SendShareButtonProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLocationProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCommandProcessor;
use BeachVolleybot\Processors\UserProcessors\UserStartCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final class ProcessorRegistryTest extends ProcessorTestCase
{
    private ProcessorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = ProcessorRegistryFactory::create();
    }

    public function testResolvesInlineCallbackQueryToGameQueueAndJoinProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_inline', inlineQueryId: 'iq_inline');
        $update = TelegramUpdate::fromArray($this->callbackQueryPayload('msg_inline', '{"a":"j"}'));

        $this->assertSame('game_' . $gameId, $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(JoinProcessor::class, $this->registry->resolveProcessor($update, $this->telegramSender));
    }

    public function testResolvesAdminCallbackQueryToDmQueueAndSettingsMenuCallbackProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"aa":"st"}'));

        $this->assertSame('dm_12345678', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            SettingsMenuCallbackProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesUserCallbackQueryToDmQueueAndUserGamesListCallbackProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"ua":"ugl"}', fromId: 555, chatId: 555));

        $this->assertSame('dm_555', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserGamesListCallbackProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesEditedLocationReplyToGameQueueAndSetLiveLocationProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_edit', inlineQueryId: 'iq_edit');
        $update = TelegramUpdate::fromArray(
            $this->editedLocationMessagePayload(latitude: 41.4, longitude: 2.2, inlineQueryId: 'iq_edit'),
        );

        $this->assertSame('game_' . $gameId, $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            SetLiveLocationProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesLocationReplyToGameQueueAndSetLocationProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_loc', inlineQueryId: 'iq_loc');
        $update = TelegramUpdate::fromArray(
            $this->locationMessagePayload(latitude: 41.4, longitude: 2.2, inlineQueryId: 'iq_loc'),
        );

        $this->assertSame('game_' . $gameId, $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            SetLocationProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesTimeOnlyReplyToGameQueueAndJoinWithTimeProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_time', inlineQueryId: 'iq_time');
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('18:00', 'iq_time'));

        $this->assertSame('game_' . $gameId, $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            JoinWithTimeProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesNonTimeReplyToGameQueueAndChangeTitleProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_title', inlineQueryId: 'iq_title');
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('New title', 'iq_title'));

        $this->assertSame('game_' . $gameId, $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            ChangeTitleProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPinNotificationToPinQueueAndDeletePinNotificationProcessor(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->pinNotificationPayload(chatId: -100, messageId: 11, pinnedMessageId: 10),
        );

        $this->assertSame('pin_-100', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            DeletePinNotificationProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesViaBotKeyboardMessageToPinQueueAndCompositeProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->viaBotKeyboardMessagePayload(chatId: -200));

        $this->assertSame('pin_-200', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            CompositeProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateGamesCommandToDmQueueAndUserGamesListCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/games', fromId: 555));

        $this->assertSame('dm_555', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserGamesListCommandProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateStartCommandToDmQueueAndUserStartCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/start', fromId: 555));

        $this->assertSame('dm_555', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserStartCommandProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesAdminSettingsCommandToDmQueueAndSettingsMenuCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings', fromId: 12345678));

        $this->assertSame('dm_12345678', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            SettingsMenuCommandProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateViaBotGameShareToDmQueueAndSendShareButtonProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateViaBotGameMessagePayload('iq_share', fromId: 555));

        $this->assertSame('dm_555', $this->registry->resolveQueueName($update));
        $this->assertInstanceOf(
            SendShareButtonProcessor::class,
            $this->registry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testReturnsNullForNonAdminSettingsCommand(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings', fromId: 999));

        $this->assertNull($this->registry->resolveQueueName($update));
        $this->assertNull($this->registry->resolveProcessor($update, $this->telegramSender));
    }

    public function testReturnsNullForArbitraryPrivateText(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('hello there', fromId: 555));

        $this->assertNull($this->registry->resolveQueueName($update));
        $this->assertNull($this->registry->resolveProcessor($update, $this->telegramSender));
    }

    public function testReturnsNullForInlineQuery(): void
    {
        $update = TelegramUpdate::fromArray($this->inlineQueryPayload(inlineQueryId: 'iq_x', query: 'anything'));

        $this->assertNull($this->registry->resolveQueueName($update));
        $this->assertNull($this->registry->resolveProcessor($update, $this->telegramSender));
    }

    public function testReturnsNullQueueWhenGameLookupFailsButHandlerMatches(): void
    {
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('18:00', 'iq_missing'));

        $this->assertNull($this->registry->resolveQueueName($update));
    }
}
