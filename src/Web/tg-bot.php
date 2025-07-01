<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use BeachVolleybot\Security\TgBotValidator;
use BeachVolleybot\Webhook\IncomingMessageDTO;
use TelegramBot\Api\BotApi;
use BeachVolleybot\App;

$securityToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? $argv[1] ?? '';

$tgBotValidator = new TgBotValidator($securityToken);
if (!$tgBotValidator->validate()) {
    http_response_code(403);
    exit('Forbidden');
}

$bot = new BotApi(TG_BOT_ACCESS_TOKEN);
$payload = file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    exit('Bad Request: No payload received');
}

$payload = json_decode($payload, true);
$incomingMessageDTO = new IncomingMessageDTO($payload);
$app = new App($bot, $incomingMessageDTO);
$app->run();
