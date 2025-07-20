<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use BeachVolleybot\App;
use BeachVolleybot\Common\Logger;
use BeachVolleybot\Validator\Rules\MeaningfulPayloadRule;
use BeachVolleybot\Validator\Rules\PostRequestRule;
use BeachVolleybot\Validator\Rules\TelegramSecretTokenRule;
use BeachVolleybot\Validator\Validator;
use BeachVolleybot\Webhook\IncomingMessageDTO;
use TelegramBot\Api\BotApi;

$secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? $argv[1] ?? '';
$payload = file_get_contents('php://input');
$validator = new Validator(
    [
        new TelegramSecretTokenRule($secretToken),
        new PostRequestRule(),
        new MeaningfulPayloadRule($payload),
    ]
);
$validationResult = $validator->validate();
if (!$validationResult->isSuccess()) {
    Logger::logUnauthorizedAccessAttempt($validationResult->getError());

    http_response_code(403);
    exit('Forbidden');
}

$payload = json_decode($payload, true);
$incomingMessageDTO = new IncomingMessageDTO($payload);
$bot = new BotApi(TG_BOT_ACCESS_TOKEN);
$app = new App($bot, $incomingMessageDTO);
$app->run();
