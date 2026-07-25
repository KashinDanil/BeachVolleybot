<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

/**
 * A handler whose work is deferred to a queue instead of running inside the webhook request.
 *
 * matches() runs twice for these: once at routing to pick the queue, again at worker
 * dispatch to pick the processor. That is why it must stay pure.
 */
abstract readonly class AbstractQueuedProcessorHandler extends AbstractProcessorHandler
{
    abstract public function routeToQueue(TelegramUpdate $update): ?string;
}
