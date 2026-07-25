<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Routing;

use BeachVolleybot\Processors\ProcessorRegistryFactory;
use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Routing\IncomingMessageRouter;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Integration\Processors\Stub\BotApiStub;
use BeachVolleybot\Tests\Unit\Queue\Stub\SpyQueue;
use PHPUnit\Framework\TestCase;

final class IncomingMessageRouterTest extends TestCase
{
    private BotApiStub $bot;
    private IncomingMessageRouter $router;

    protected function setUp(): void
    {
        @mkdir(BASE_LOG_DIR, 0777, true);
        SpyQueue::reset();

        $this->bot = new BotApiStub();
        $queueRouter = new IncomingMessageQueueRouter(SpyQueue::class, '/tmp/test_queues', ProcessorRegistryFactory::createQueued());
        $this->router = new IncomingMessageRouter(
            new TelegramMessageSender($this->bot),
            ProcessorRegistryFactory::createImmediate(),
            $queueRouter,
        );
    }

    public function testChosenInlineResultWithoutInlineMessageIdIsDropped(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'chosen_inline_result' => [
                'result_id' => 'r1',
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'query' => 'Beach Volleyball 18:00',
            ],
        ]);

        $this->router->route($update);

        $this->assertSame([], $this->bot->calls);
        $this->assertSame([], SpyQueue::$instances);
    }
}
