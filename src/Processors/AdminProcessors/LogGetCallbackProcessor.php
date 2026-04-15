<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class LogGetCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $filename = $this->adminCallbackData->getFilename() ?? '';

        if (!LogFileRepository::isValidFilename($filename)) {
            $this->answerCallbackQuery($update->callbackQuery, LogFileRepository::INVALID_FILENAME);

            return;
        }

        $logFileRepository = new LogFileRepository();

        if (!$logFileRepository->exists($filename)) {
            $this->answerCallbackQuery($update->callbackQuery, 'File not found');

            return;
        }

        $this->telegramSender->sendDocument($update->callbackQuery->message->chat->id, $logFileRepository->path($filename));
        $this->answerCallbackQuery($update->callbackQuery, 'Sending...');
    }
}
