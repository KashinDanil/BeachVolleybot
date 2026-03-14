<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use BeachVolleybot\App;
use BeachVolleybot\Common\InputStrategy\InputStrategyFactory;
use BeachVolleybot\Common\Logger;
use BeachVolleybot\Validator\Rules\ValidPayloadRule;
use BeachVolleybot\Validator\Rules\PostRequestRule;
use BeachVolleybot\Validator\Rules\AppSecretTokenRule;
use BeachVolleybot\Validator\Validator;
use BeachVolleybot\Webhook\IncomingMessageDTO;
use TelegramBot\Api\BotApi;

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

$payload = json_decode($inputStrategy->getPayload(), true);
$incomingMessageDTO = new IncomingMessageDTO($payload);
$bot = new BotApi(TG_BOT_ACCESS_TOKEN);
$app = new App($bot, $incomingMessageDTO);
$app->run();
