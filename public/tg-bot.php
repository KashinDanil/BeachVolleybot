<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use BeachVolleybot\Common\InputStrategies\InputStrategyFactory;
use BeachVolleybot\Common\Logger;
use BeachVolleybot\Routing\IncomingMessageQueueRouter;
use BeachVolleybot\Routing\IncomingMessageRouter;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Validator\Rules\ValidPayloadRule;
use BeachVolleybot\Validator\Rules\PostRequestRule;
use BeachVolleybot\Validator\Rules\AppSecretTokenRule;
use BeachVolleybot\Validator\Validator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\RateLimitedBotApi;

$inputStrategy = InputStrategyFactory::getStrategy();
$validator = new Validator(
    [
        new PostRequestRule($inputStrategy->getRequestMethod()),
        new AppSecretTokenRule($inputStrategy->getSecretToken()),
        new ValidPayloadRule($inputStrategy->getPayload()),
    ]
);
$validationResult = $validator->validateAll();
if (!$validationResult->isSuccess()) {
    Logger::logUnauthorizedAccessAttempt($validationResult->getError());

    http_response_code(403);
    exit('Forbidden');
}

Logger::logVerbose(sprintf('Received message: %s' . PHP_EOL, $inputStrategy->getPayload()));
$payload = json_decode($inputStrategy->getPayload(), true);
$update = TelegramUpdate::fromArray($payload);

$telegramSender = new TelegramMessageSender(new RateLimitedBotApi(TG_BOT_ACCESS_TOKEN, TG_MAX_REQUESTS_PER_SECOND));
$queueRouter = new IncomingMessageQueueRouter(QUEUE_CLASS, BASE_QUEUE_DIR);
$router = new IncomingMessageRouter($telegramSender, $queueRouter);
$router->route($update);
