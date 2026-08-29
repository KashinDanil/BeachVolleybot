<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\GroupHelpCommandProcessor;
use BeachVolleybot\Telegram\MessageBuilders\HelpMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class GroupHelpCommandProcessorTest extends ProcessorTestCase
{
    private const int SENDER_ID = 311830743;
    private const int CHAT_ID = -1003759398496;
    private const int EPHEMERAL_MESSAGE_ID = 251718676;
    private const int MESSAGE_THREAD_ID = 328;

    public function testRepliesToTheSenderRatherThanTheReceiverUser(): void
    {
        $this->processCommand();

        $params = $this->lastEphemeralSendParams();
        $this->assertNotNull($params, 'Expected an ephemeral sendMessage');

        // receiver_user on the incoming command is the bot itself, so addressing the
        // reply with it would send the help to the bot instead of the person asking.
        $this->assertSame(self::SENDER_ID, $params['receiver_user_id']);
        $this->assertSame(self::CHAT_ID, $params['chat_id']);
    }

    public function testRepliesToTheIncomingEphemeralMessage(): void
    {
        $this->processCommand();

        $replyParameters = json_decode($this->lastEphemeralSendParams()['reply_parameters'], true);

        $this->assertSame(self::EPHEMERAL_MESSAGE_ID, $replyParameters['ephemeral_message_id']);
    }

    public function testSendsTheGroupShapedHelpBody(): void
    {
        $this->processCommand();

        $params = $this->lastEphemeralSendParams();
        $expected = new HelpMessageBuilder(new Translator())->build(BOT_USERNAME, true);

        $this->assertSame($expected->getText()->getMessageText(), $params['text']);
        $this->assertSame('MarkdownV2', $params['parse_mode']);
    }

    public function testExplainsSharingFromTheBotsDmAndThatCopiesStayInSync(): void
    {
        $this->processCommand();

        $text = $this->lastEphemeralSendParams()['text'];

        $this->assertStringContainsString('*To share a game across multiple chats*', $text);
        $this->assertStringContainsString('/games command in my DM', $text);
        $this->assertStringContainsString('stays in sync everywhere', $text);
    }

    public function testMentionsTheNewGameWizardAsAnAlternativeToMentioningTheBot(): void
    {
        $this->processCommand();

        // In a group the bare command is ambiguous with other bots, so the help
        // text has to spell out the @mention form instead. Strip the MarkdownV2
        // escaping backslashes before matching so the assertion doesn't have to
        // account for BOT_USERNAME's own underscore being escaped too.
        $text = str_replace('\\', '', $this->lastEphemeralSendParams()['text']);

        $this->assertStringContainsString('/new_game@' . BOT_USERNAME, $text);
        $this->assertStringContainsString('help you create a game', $text);
    }

    public function testExplainsThatOnlyTheCreatorCanChangeAGameByReplyingToIt(): void
    {
        $this->processCommand();

        $text = $this->lastEphemeralSendParams()['text'];

        $this->assertStringContainsString('*To change the game*', $text);
        // The reply is dropped silently when the new text loses its date or time,
        // so the help has to spell that requirement out.
        $this->assertStringContainsString('keep a date and time in it', $text);
        $this->assertStringContainsString('created the game can do this', $text);
    }

    public function testRepliesInsideTheForumTopicTheCommandCameFrom(): void
    {
        $this->processCommand();

        // Without the topic id the reply lands outside the topic and nobody sees it.
        // The command itself carries no thread id — only its reply_to_message does.
        $this->assertSame(self::MESSAGE_THREAD_ID, $this->lastEphemeralSendParams()['message_thread_id']);
    }

    public function testOmitsTheTopicIdInAGroupWithoutTopics(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->ephemeralGroupMessagePayload(fromId: self::SENDER_ID, messageThreadId: null),
        );

        new GroupHelpCommandProcessor($this->telegramSender)->process($update);

        $this->assertArrayNotHasKey('message_thread_id', $this->lastEphemeralSendParams());
    }

    public function testLocalizesTheReplyToTheSenderLanguage(): void
    {
        $this->processCommand(languageCode: 'ru');

        $this->assertStringContainsString('*Чтобы присоединиться к игре или выйти из неё*', $this->lastEphemeralSendParams()['text']);
    }

    private function processCommand(?string $languageCode = null): void
    {
        $update = TelegramUpdate::fromArray(
            $this->ephemeralGroupMessagePayload(fromId: self::SENDER_ID, languageCode: $languageCode),
        );

        new GroupHelpCommandProcessor($this->telegramSender)->process($update);
    }
}
