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

    public function testSendsTheSameHelpBodyAsTheDmCommand(): void
    {
        $this->processCommand();

        $params = $this->lastEphemeralSendParams();
        $expected = new HelpMessageBuilder(new Translator())->build(BOT_USERNAME);

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

    public function testDoesNotDeleteAnything(): void
    {
        $this->processCommand();

        // The command is ephemeral and carries message_id 0 — there is nothing to delete,
        // so the consume-then-delete convention of the DM commands must not apply.
        $deleteCalls = array_filter($this->bot->calls, static fn(array $call): bool => 'deleteMessage' === $call['method']);
        $this->assertEmpty($deleteCalls);
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
