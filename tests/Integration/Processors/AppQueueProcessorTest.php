<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCommandProcessor;
use BeachVolleybot\Processors\AppQueueProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Processors\UpdateProcessors\DeletePinNotificationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\PinMessageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SendShareButtonProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLocationProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGameDetailCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Integration\Processors\Stub\ProcessorSelectionRecorder;
use DanilKashin\FileQueue\Queue\QueueMessage;

final class AppQueueProcessorTest extends ProcessorTestCase
{
    private AppQueueProcessor $processor;
    private ProcessorSelectionRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new ProcessorSelectionRecorder();
        $this->processor = new readonly class($this->telegramSender, $this->recorder) extends AppQueueProcessor {
            public function __construct(
                private TelegramMessageSender $injectedSender,
                private ProcessorSelectionRecorder $recorder,
            ) {
                parent::__construct();
            }

            protected function createTelegramSender(): TelegramMessageSender
            {
                return $this->injectedSender;
            }

            protected function resolveProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
            {
                $processor = parent::resolveProcessor($update, $telegramSender);
                $this->recorder->selections[] = null !== $processor ? $processor::class : null;

                return null;
            }
        };
    }

    public function testRoutesPinNotificationToDeletePinNotificationProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->pinNotificationPayload(chatId: -100, messageId: 11, pinnedMessageId: 10)));

        $this->assertSame([DeletePinNotificationProcessor::class], $this->recorder->selections);
    }

    public function testRoutesEditedMessageToSetLiveLocationProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->editedLocationMessagePayload(latitude: 41.4, longitude: 2.2, gameKey: 'q1')));

        $this->assertSame([SetLiveLocationProcessor::class], $this->recorder->selections);
    }

    public function testRoutesGroupLocationMessageToSetLocationProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->locationMessagePayload(latitude: 41.4, longitude: 2.2, gameKey: 'q1')));

        $this->assertSame([SetLocationProcessor::class], $this->recorder->selections);
    }

    public function testRoutesTimeOnlyReplyToJoinWithTimeProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->replyMessagePayload(text: '18:00', gameKey: 'q1')));

        $this->assertSame([JoinWithTimeProcessor::class], $this->recorder->selections);
    }

    public function testRoutesRenameByCreatorToChangeTitleProcessor(): void
    {
        $this->seedFullGame(inlineMessageId: 'msg_q1', gameKey: 'q1', createdBy: 200);

        $this->processor->process(new QueueMessage($this->replyMessagePayload(text: 'Bogatell 31.12.2099 20:00', gameKey: 'q1')));

        $this->assertSame([ChangeTitleProcessor::class], $this->recorder->selections);
    }

    public function testRoutesReplyWithTimeInsideTextToJoinWithTimeProcessor(): void
    {
        $this->seedFullGame(inlineMessageId: 'msg_q1', gameKey: 'q1', createdBy: 200);

        $this->processor->process(new QueueMessage($this->replyMessagePayload(text: 'I can make it by 18:00', gameKey: 'q1')));

        $this->assertSame([JoinWithTimeProcessor::class], $this->recorder->selections);
    }

    public function testRoutesTitleReplyFromNonCreatorToJoinWithTimeProcessor(): void
    {
        $this->seedFullGame(inlineMessageId: 'msg_q1', gameKey: 'q1', createdBy: 999);

        $this->processor->process(new QueueMessage($this->replyMessagePayload(text: 'Bogatell 31.12.2099 20:00', gameKey: 'q1')));

        $this->assertSame([JoinWithTimeProcessor::class], $this->recorder->selections);
    }

    public function testRoutesNoProcessorForReplyWithoutTimeOrTitle(): void
    {
        $this->seedFullGame(inlineMessageId: 'msg_q1', gameKey: 'q1', createdBy: 200);

        $this->processor->process(new QueueMessage($this->replyMessagePayload(text: 'sounds good to me', gameKey: 'q1')));

        $this->assertSame([null], $this->recorder->selections);
    }

    public function testRoutesViaBotMessageWithKeyboardToPinMessageProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->viaBotKeyboardMessagePayload()));

        $this->assertSame([PinMessageProcessor::class], $this->recorder->selections);
    }

    public function testRoutesAdminPrivateSettingsCommandToSettingsMenuProcessor(): void
    {
        $this->seedAdmin();

        $this->processor->process(new QueueMessage($this->privateMessagePayload(text: '/settings', fromId: self::ADMIN_TELEGRAM_USER_ID)));

        $this->assertSame([SettingsMenuCommandProcessor::class], $this->recorder->selections);
    }

    public function testRoutesPrivateGamesCommandToUserGamesListCommandProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->privateMessagePayload(text: '/games', fromId: 555)));

        $this->assertSame([UserGamesListCommandProcessor::class], $this->recorder->selections);
    }

    public function testRoutesPrivateViaBotGameMessageToSendShareButtonProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->privateViaBotGameMessagePayload(gameKey: 'query_42')));

        $this->assertSame([SendShareButtonProcessor::class], $this->recorder->selections);
    }

    public function testRoutesPrivateLocationReplyToSetLocationProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->privateLocationMessagePayload(latitude: 41.4, longitude: 2.2, gameKey: 'q1')));

        $this->assertSame([SetLocationProcessor::class], $this->recorder->selections);
    }

    public function testRoutesPrivateTimeOnlyReplyToJoinWithTimeProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->privateReplyMessagePayload(text: '18:00', gameKey: 'q1')));

        $this->assertSame([JoinWithTimeProcessor::class], $this->recorder->selections);
    }

    public function testRoutesPrivateRenameByCreatorToChangeTitleProcessor(): void
    {
        $this->seedFullGame(inlineMessageId: 'msg_q1', gameKey: 'q1', createdBy: 200);

        $this->processor->process(new QueueMessage($this->privateReplyMessagePayload(text: 'Bogatell 31.12.2099 20:00', gameKey: 'q1')));

        $this->assertSame([ChangeTitleProcessor::class], $this->recorder->selections);
    }

    public function testReturnsNullForNonAdminPrivateSettingsCommand(): void
    {
        $this->processor->process(new QueueMessage($this->privateMessagePayload(text: '/settings', fromId: 999)));

        $this->assertSame([null], $this->recorder->selections);
    }

    public function testRoutesNonAdminCallbackQueryViaCallbackData(): void
    {
        $this->processor->process(new QueueMessage($this->callbackQueryPayload(inlineMessageId: 'msg_1', data: '{"a":"j"}')));

        $this->assertSame([JoinProcessor::class], $this->recorder->selections);
    }

    public function testRoutesAdminCallbackQueryViaAdminCallbackData(): void
    {
        $this->seedAdmin();

        $this->processor->process(new QueueMessage($this->adminCallbackQueryPayload(data: '{"aa":"st"}')));

        $this->assertSame([SettingsMenuCallbackProcessor::class], $this->recorder->selections);
    }

    public function testRoutesUserGamesListCallbackToUserGamesListCallbackProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->adminCallbackQueryPayload(data: '{"ua":"ugl"}', fromId: 555, chatId: 555)));

        $this->assertSame([UserGamesListCallbackProcessor::class], $this->recorder->selections);
    }

    public function testRoutesUserGameDetailCallbackToUserGameDetailCallbackProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->adminCallbackQueryPayload(data: '{"ua":"ugd","g":1}', fromId: 555, chatId: 555)));

        $this->assertSame([UserGameDetailCallbackProcessor::class], $this->recorder->selections);
    }

    public function testReturnsNullForInlineQuery(): void
    {
        $this->processor->process(new QueueMessage($this->inlineQueryPayload(gameKey: 'q1', query: 'whatever')));

        $this->assertSame([null], $this->recorder->selections);
    }

    public function testReturnsNullForUnrecognizedUpdate(): void
    {
        $this->processor->process(new QueueMessage(['update_id' => 1]));

        $this->assertSame([null], $this->recorder->selections);
    }

    public function testDuplicateUpdateIdIsNotResolvedTwice(): void
    {
        $payload = $this->pinNotificationPayload(chatId: -100, messageId: 11, pinnedMessageId: 10);

        $this->processor->process(new QueueMessage($payload));
        $this->processor->process(new QueueMessage($payload));

        $this->assertSame([DeletePinNotificationProcessor::class], $this->recorder->selections);
    }
}
