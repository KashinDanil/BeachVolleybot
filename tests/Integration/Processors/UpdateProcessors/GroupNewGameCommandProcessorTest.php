<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\GroupNewGameCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class GroupNewGameCommandProcessorTest extends ProcessorTestCase
{
    private const int EPHEMERAL_SENDER_ID = 311830743; // ephemeralGroupMessagePayload default
    private const int FORUM_THREAD_ID = 328;

    public function testSendsTheDatePickerEphemerallyIntoTheTopic(): void
    {
        $update = TelegramUpdate::fromArray(
            $this->ephemeralGroupMessagePayload(text: '/new_game@' . BOT_USERNAME),
        );

        new GroupNewGameCommandProcessor($this->telegramSender)->process($update);

        $params = $this->lastEphemeralSendParams();
        $this->assertNotNull($params);
        $this->assertSame(self::EPHEMERAL_SENDER_ID, $params['receiver_user_id']);
        $this->assertSame(self::FORUM_THREAD_ID, $params['message_thread_id']);
    }
}
