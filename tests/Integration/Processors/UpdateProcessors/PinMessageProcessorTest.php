<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\PinMessageProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class PinMessageProcessorTest extends ProcessorTestCase
{
    private const int CHAT_ID = -5127803306;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../../migrations/002_create_pinned_messages.sql'));
    }

    public function testUnpinsAfterMidnightFollowingTheDateInTheCard(): void
    {
        new PinMessageProcessor($this->telegramSender)->process($this->buildUpdate('Bogatell 01.01.2020 18:00'));

        $this->assertSame('2020-01-02 00:00:00', $this->storedUnpinAfter());
    }

    public function testUnpinsAfterMidnightRegardlessOfTheKickoffHour(): void
    {
        new PinMessageProcessor($this->telegramSender)->process($this->buildUpdate('Bogatell 01.01.2020 23:45'));

        $this->assertSame('2020-01-02 00:00:00', $this->storedUnpinAfter());
    }

    public function testLeavesNoExpiryWhenTheCardCarriesNoDate(): void
    {
        // The games row is written by a different update, so the card text is all this has to go on.
        new PinMessageProcessor($this->telegramSender)->process($this->buildUpdate('Bogatell 18:00'));

        $this->assertNull($this->storedUnpinAfter());
    }

    private function storedUnpinAfter(): ?string
    {
        $rows = $this->db->select('pinned_messages', '*', ['chat_id' => self::CHAT_ID]);
        $this->assertCount(1, $rows);

        return $rows[0]['unpin_after'];
    }

    private function buildUpdate(string $cardText): TelegramUpdate
    {
        $payload = $this->viaBotKeyboardMessagePayload(self::CHAT_ID);
        $payload['message']['text'] = $cardText;

        return TelegramUpdate::fromArray($payload);
    }
}
