<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use BeachVolleybot\security\TgBotValidator;
use TelegramBot\Api\BotApi;
use BeachVolleybot\App;

$securityToken = $_GET['token'] ?? $argv[1] ?? '';

$tgBotValidator = new TgBotValidator($securityToken);
if (!$tgBotValidator->validate()) {
    http_response_code(403);
    exit('Forbidden');
}

$bot = new BotApi(TG_BOT_ACCESS_TOKEN);
$app = new App($bot, $_POST);
$app->run();
