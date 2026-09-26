<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCommandProcessor;
use BeachVolleybot\Processors\ProcessorRegistry;
use BeachVolleybot\Processors\ProcessorRegistryFactory;
use BeachVolleybot\Processors\UpdateProcessors\ChangeTitleProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Processors\UpdateProcessors\DeletePinNotificationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\EditedViaBotMessageProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ForwardGameProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\JoinProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GroupHelpCommandProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GroupNewGameCommandProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineQueryProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGame\NewGameConfirmProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGame\NewGamePickVenueProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGame\NewGameSendProcessor;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Processors\UpdateProcessors\SendShareButtonProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLiveLocationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLocationProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ViaBotMessageProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCallbackProcessor;
use BeachVolleybot\Processors\UserProcessors\UserGamesListCommandProcessor;
use BeachVolleybot\Processors\UserProcessors\UserHelpCommandProcessor;
use BeachVolleybot\Processors\UserProcessors\UserNewGameCommandProcessor;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

final class ProcessorRegistryTest extends ProcessorTestCase
{
    private ProcessorRegistry $queuedRegistry;
    private ProcessorRegistry $immediateRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queuedRegistry = ProcessorRegistryFactory::createQueued();
        $this->immediateRegistry = ProcessorRegistryFactory::createImmediate();
    }

    public function testResolvesInlineCallbackQueryToGameQueueAndJoinProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_inline', gameKey: 'iq_inline');
        $update = TelegramUpdate::fromArray($this->callbackQueryPayload('msg_inline', '{"a":"j"}'));

        $this->assertSame('game_' . $gameId, $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(JoinProcessor::class, $this->queuedRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testResolvesAdminCallbackQueryToDmQueueAndSettingsMenuCallbackProcessor(): void
    {
        $this->seedAdmin();
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"aa":"st"}'));

        $this->assertSame('dm_12345678', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            SettingsMenuCallbackProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesUserCallbackQueryToDmQueueAndUserGamesListCallbackProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->adminCallbackQueryPayload('{"ua":"ugl"}', fromId: 555, chatId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserGamesListCallbackProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesEditedLocationReplyToGameQueueAndSetLiveLocationProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_edit', gameKey: 'iq_edit');
        $update = TelegramUpdate::fromArray(
            $this->editedLocationMessagePayload(latitude: 41.4, longitude: 2.2, gameKey: 'iq_edit'),
        );

        $this->assertSame('game_' . $gameId, $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            SetLiveLocationProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesLocationReplyToGameQueueAndSetLocationProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_loc', gameKey: 'iq_loc');
        $update = TelegramUpdate::fromArray(
            $this->locationMessagePayload(latitude: 41.4, longitude: 2.2, gameKey: 'iq_loc'),
        );

        $this->assertSame('game_' . $gameId, $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            SetLocationProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesTimeOnlyReplyToGameQueueAndJoinWithTimeProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_time', gameKey: 'iq_time');
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('18:00', 'iq_time'));

        $this->assertSame('game_' . $gameId, $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            JoinWithTimeProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesRenameByCreatorToGameQueueAndChangeTitleProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_title', gameKey: 'iq_title', createdBy: 200);
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('Bogatell 31.12.2099 20:00', 'iq_title'));

        $this->assertSame('game_' . $gameId, $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            ChangeTitleProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesReplyWithTimeInsideTextToGameQueueAndJoinWithTimeProcessor(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_mixed', gameKey: 'iq_mixed', createdBy: 200);
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('I can make it by 18:00', 'iq_mixed'));

        $this->assertSame('game_' . $gameId, $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            JoinWithTimeProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesTitleReplyFromNonCreatorToJoinWithTimeProcessor(): void
    {
        // The rename is refused by routing, so the reply's time lands on a slot instead.
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_other', gameKey: 'iq_other', createdBy: 999);
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('Bogatell 31.12.2099 20:00', 'iq_other'));

        $this->assertSame('game_' . $gameId, $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            JoinWithTimeProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesNoProcessorForReplyWithoutTimeOrTitle(): void
    {
        $this->seedFullGame(inlineMessageId: 'msg_chat', gameKey: 'iq_chat', createdBy: 200);
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('sounds good to me', 'iq_chat'));

        $this->assertNull($this->queuedRegistry->resolveQueueName($update));
        $this->assertNull($this->queuedRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testResolvesPinNotificationToViaBotQueueAndDeletePinNotificationProcessor(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->pinNotificationPayload(chatId: -100, messageId: 11, pinnedMessageId: 10),
        );

        $this->assertSame('group_chat_-100', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            DeletePinNotificationProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesViaBotKeyboardMessageToViaBotQueueAndViaBotMessageProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->viaBotKeyboardMessagePayload(chatId: -200));

        $this->assertSame('group_chat_-200', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            ViaBotMessageProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesEditedViaBotMessageToGroupChatQueueAndEditedViaBotMessageProcessor(): void
    {
        $payload = $this->viaBotKeyboardMessagePayload(chatId: -300);
        $payload['edited_message'] = $payload['message'];
        unset($payload['message']);
        $update = TelegramUpdate::fromArray($payload);

        $this->assertSame('group_chat_-300', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            EditedViaBotMessageProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateGamesCommandToDmQueueAndUserGamesListCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/games', fromId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserGamesListCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateHelpCommandToDmQueueAndUserHelpCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/help', fromId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserHelpCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateStartCommandToDmQueueAndUserHelpCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/start', fromId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserHelpCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateHelpPrivateCommandToDmQueueAndUserHelpCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/help_private', fromId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserHelpCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesAdminSettingsCommandToDmQueueAndSettingsMenuCommandProcessor(): void
    {
        $this->seedAdmin();
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings', fromId: self::ADMIN_TELEGRAM_USER_ID));

        $this->assertSame('dm_12345678', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            SettingsMenuCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateViaBotGameShareToDmQueueAndSendShareButtonProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateViaBotGameMessagePayload('iq_share', fromId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            SendShareButtonProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testReturnsNullForNonAdminSettingsCommand(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/settings', fromId: 999));

        $this->assertNull($this->queuedRegistry->resolveQueueName($update));
        $this->assertNull($this->queuedRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testReturnsNullForArbitraryPrivateText(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('hello there', fromId: 555));

        $this->assertNull($this->queuedRegistry->resolveQueueName($update));
        $this->assertNull($this->queuedRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testReturnsNullForInlineQuery(): void
    {
        $update = TelegramUpdate::fromArray($this->inlineQueryPayload(gameKey: 'iq_x', query: 'anything'));

        $this->assertNull($this->queuedRegistry->resolveQueueName($update));
        $this->assertNull($this->queuedRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testReturnsNullQueueWhenGameLookupFailsButHandlerMatches(): void
    {
        $update = TelegramUpdate::fromArray($this->replyMessagePayload('18:00', 'iq_missing'));

        $this->assertNull($this->queuedRegistry->resolveQueueName($update));
    }

    public function testImmediateRegistryResolvesInlineQueryToInlineQueryProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->inlineQueryPayload(gameKey: 'iq_x', query: 'anything'));

        $this->assertInstanceOf(
            InlineQueryProcessor::class,
            $this->immediateRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testImmediateRegistryResolvesChosenInlineResultToCreateGameProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->chosenInlineResultPayload(
            inlineMessageId: 'imi_new',
            resultId: 'r1',
            query: 'Beach Volleyball 31.12.2099 18:00',
        ));

        $this->assertInstanceOf(
            CreateGameProcessor::class,
            $this->immediateRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testImmediateRegistryResolvesForwardQueryToForwardGameProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->chosenInlineResultPayload(
            inlineMessageId: 'imi_fwd',
            resultId: 'r1',
            query: ForwardGameQueryExtractor::PREFIX . ' 42',
        ));

        $this->assertInstanceOf(
            ForwardGameProcessor::class,
            $this->immediateRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testImmediateRegistryIgnoresChosenInlineResultWithoutInlineMessageId(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'chosen_inline_result' => [
                'result_id' => 'r1',
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'query' => 'Beach Volleyball 31.12.2099 18:00',
            ],
        ]);

        $this->assertNull($this->immediateRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testImmediateRegistryNeverResolvesAQueueName(): void
    {
        $update = TelegramUpdate::fromArray($this->inlineQueryPayload(gameKey: 'iq_x', query: 'anything'));

        $this->assertNull($this->immediateRegistry->resolveQueueName($update));
    }

    public function testResolvesGroupEphemeralHelpCommandToGroupHelpProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload());

        $this->assertInstanceOf(
            GroupHelpCommandProcessor::class,
            $this->immediateRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testQueuedRegistryIgnoresGroupEphemeralHelpCommand(): void
    {
        $update = TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload());

        $this->assertNull($this->queuedRegistry->resolveQueueName($update));
        $this->assertNull($this->queuedRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testNeitherRegistryAnswersAPlainVisibleGroupHelpCommand(): void
    {
        $update = TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload(ephemeralMessageId: null));

        $this->assertNull($this->immediateRegistry->resolveProcessor($update, $this->telegramSender));
        $this->assertNull($this->queuedRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testImmediateRegistryIgnoresQueuedUpdates(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/help', fromId: 555));

        $this->assertNull($this->immediateRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testImmediateRegistryResolvesGroupEphemeralNewGameCommandToGroupNewGameCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload(text: '/new_game@' . BOT_USERNAME));

        $this->assertInstanceOf(
            GroupNewGameCommandProcessor::class,
            $this->immediateRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateNewGameCommandToDmQueueAndUserNewGameCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/new_game', fromId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserNewGameCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesPrivateMentionedNewGameCommandToUserNewGameCommandProcessor(): void
    {
        // Some clients still send the @mention form in a private chat (e.g. tapping
        // a command copied from a group), so DMs accept it too, not just the bare form.
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/new_game@' . BOT_USERNAME, fromId: 555));

        $this->assertInstanceOf(
            UserNewGameCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testImmediateRegistryIgnoresPrivateNewGameCommand(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/new_game', fromId: 555));

        $this->assertNull($this->immediateRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testResolvesPrivateNewGamePrivateCommandToDmQueueAndUserNewGameCommandProcessor(): void
    {
        $update = TelegramUpdate::fromArray($this->privateMessagePayload('/new_game_private', fromId: 555));

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            UserNewGameCommandProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
    }

    public function testResolvesNewGameWizardCallbackToDmQueueAndPickVenueProcessor(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_ng',
                'from' => ['id' => 555, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => 900,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => 555, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'New game — Step 3 of 4',
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::PickVenue)->withVenueName('Bogatell')->toJson(),
            ],
        ]);

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            NewGamePickVenueProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
        $this->assertNull($this->immediateRegistry->resolveProcessor($update, $this->telegramSender));
    }

    public function testResolvesNewGameConfirmPageCallbacksToDmQueueAndConfirmProcessor(): void
    {
        // Every action that only ever fires while already on the confirm page — a language
        // switch or a players-per-net change — shares one processor, since none of them need
        // the venue on the callback: it reads back from the text already on screen.
        $actions = [
            NewGameCallbackAction::SetLanguage,
            NewGameCallbackAction::AdjustPlayersPerNet,
            NewGameCallbackAction::SetPlayersPerNet,
            NewGameCallbackAction::RemovePlayersPerNet,
        ];

        foreach ($actions as $action) {
            $update = TelegramUpdate::fromArray([
                'update_id' => 1,
                'callback_query' => [
                    'id' => 'cbq_ng',
                    'from' => ['id' => 555, 'first_name' => 'Danil', 'is_bot' => false],
                    'chat_instance' => '-123',
                    'message' => [
                        'message_id' => 900,
                        'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                        'chat' => ['id' => 555, 'type' => 'private'],
                        'date' => 1700000000,
                        'text' => 'New game — Step 4 of 4',
                    ],
                    'data' => NewGameCallbackData::create($action)->withLanguage('ru')->withPlayersPerNet(8)->toJson(),
                ],
            ]);

            $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
            $this->assertInstanceOf(
                NewGameConfirmProcessor::class,
                $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
                "Action '{$action->value}' did not resolve to NewGameConfirmProcessor",
            );
            $this->assertNull($this->immediateRegistry->resolveProcessor($update, $this->telegramSender));
        }
    }

    public function testResolvesNewGameConfirmCallbackToDmQueueAndSendProcessor(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_ng',
                'from' => ['id' => 555, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => 900,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => 555, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'New game — Step 4 of 4',
                ],
                'data' => NewGameCallbackData::create(NewGameCallbackAction::Send)->toJson(),
            ],
        ]);

        $this->assertSame('dm_555', $this->queuedRegistry->resolveQueueName($update));
        $this->assertInstanceOf(
            NewGameSendProcessor::class,
            $this->queuedRegistry->resolveProcessor($update, $this->telegramSender),
        );
        $this->assertNull($this->immediateRegistry->resolveProcessor($update, $this->telegramSender));
    }
}
