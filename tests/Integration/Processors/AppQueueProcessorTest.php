<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Processors\AppQueueProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Processors\UpdateProcessors\DeletePinNotificationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\PinMessageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLocationProcessor;
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
        $this->processor->process(new QueueMessage($this->editedLocationMessagePayload(latitude: 41.4, longitude: 2.2, inlineQueryId: 'q1')));

        $this->assertSame([SetLiveLocationProcessor::class], $this->recorder->selections);
    }

    public function testRoutesGroupLocationMessageToSetLocationProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->locationMessagePayload(latitude: 41.4, longitude: 2.2, inlineQueryId: 'q1')));

        $this->assertSame([SetLocationProcessor::class], $this->recorder->selections);
    }

    public function testRoutesTimeOnlyReplyToJoinWithTimeProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->replyMessagePayload(text: '18:00', inlineQueryId: 'q1')));

        $this->assertSame([JoinWithTimeProcessor::class], $this->recorder->selections);
    }

    public function testRoutesNonTimeReplyToChangeTitleProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->replyMessagePayload(text: 'New title', inlineQueryId: 'q1')));

        $this->assertSame([ChangeTitleProcessor::class], $this->recorder->selections);
    }

    public function testRoutesViaBotMessageWithKeyboardToPinMessageProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->viaBotKeyboardMessagePayload()));

        $this->assertSame([PinMessageProcessor::class], $this->recorder->selections);
    }

    public function testRoutesChosenInlineResultToCreateGameProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->chosenInlineResultPayload(inlineMessageId: 'inline_new', resultId: 'result_1', query: 'Bogatell 18:00')));

        $this->assertSame([CreateGameProcessor::class], $this->recorder->selections);
    }

    public function testRoutesAdminPrivateSettingsCommandToSettingsMenuProcessor(): void
    {
        $this->processor->process(new QueueMessage($this->privateMessagePayload(text: '/settings', fromId: 12345678)));

        $this->assertSame([SettingsMenuCallbackProcessor::class], $this->recorder->selections);
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
        $this->processor->process(new QueueMessage($this->adminCallbackQueryPayload(data: '{"aa":"st"}')));

        $this->assertSame([SettingsMenuCallbackProcessor::class], $this->recorder->selections);
    }

    public function testReturnsNullForInlineQuery(): void
    {
        $this->processor->process(new QueueMessage($this->inlineQueryPayload(inlineQueryId: 'q1', query: 'whatever')));

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
