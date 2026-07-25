<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Routing;

use BeachVolleybot\Processors\ProcessorRegistryFactory;
use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Routing\IncomingMessageRouter;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;

// Telegram rejects an ephemeral reply more than 15 seconds after the command, so group
// /help has to be answered inside the request and never enqueued.
final class EphemeralHelpRoutingTest extends ProcessorTestCase
{
    private IncomingMessageRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        SpyQueue::reset();

        $this->router = new IncomingMessageRouter(
            $this->telegramSender,
            ProcessorRegistryFactory::createImmediate(),
            new IncomingMessageQueueRouter(SpyQueue::class, '/tmp/test_queues', ProcessorRegistryFactory::createQueued()),
        );
    }

    public function testEphemeralHelpIsAnsweredOnTheRequest(): void
    {
        $this->router->route(TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload()));

        $this->assertNotNull($this->lastEphemeralSendParams(), 'Expected an ephemeral reply on the request');
        $this->assertSame([], SpyQueue::$instances, 'Ephemeral help must not be queued');
    }

    public function testBareHelpCommandWithoutTheBotUsernameIsAlsoAnswered(): void
    {
        $this->router->route(TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload(text: '/help')));

        $this->assertNotNull($this->lastEphemeralSendParams());
    }

    public function testPlainVisibleHelpIsIgnored(): void
    {
        $this->router->route(
            TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload(ephemeralMessageId: null)),
        );

        $this->assertSame([], $this->bot->calls, 'A visible /help must not be answered at all');
        $this->assertSame([], SpyQueue::$instances);
    }

    public function testAnotherBotsHelpCommandIsIgnored(): void
    {
        $this->router->route(
            TelegramUpdate::fromArray($this->ephemeralGroupMessagePayload(text: '/help@some_other_bot')),
        );

        $this->assertSame([], $this->bot->calls);
        $this->assertSame([], SpyQueue::$instances);
    }

    public function testPrivateHelpStillGoesToTheQueue(): void
    {
        $this->router->route(TelegramUpdate::fromArray($this->privateMessagePayload('/help', fromId: 555)));

        $this->assertSame([], $this->bot->calls, 'DM help is answered by the worker, not on the request');
        $this->assertCount(1, SpyQueue::$instances);
        $this->assertSame('dm_555', SpyQueue::$instances[0]->queueName);
    }
}
