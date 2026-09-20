<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Root\Log;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Processors\AdminProcessors\AbstractAdminMutationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class RootLogGetCallbackProcessor extends AbstractAdminMutationProcessor
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

        $this->logAdminAction($update->callbackQuery->from, 'root_download_log', "file=$filename");
        $this->telegramSender->sendDocument($update->callbackQuery->message->chat->id, $logFileRepository->path($filename));
        $this->answerCallbackQuery($update->callbackQuery, 'Sending...');
    }
}
