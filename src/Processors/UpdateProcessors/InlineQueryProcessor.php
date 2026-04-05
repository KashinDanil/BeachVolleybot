<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Outgoing\ArticleBuilder;
use BeachVolleybot\Validator\Rules\TimeInTitleRule;
use BeachVolleybot\Validator\Validator;
use TelegramBot\Api\Exception;

class InlineQueryProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $inlineQuery = $update->inlineQuery;

        $validator = new Validator([new TimeInTitleRule($inlineQuery->query)]);

        if (!$validator->validate()->isSuccess()) {
            return;
        }

        $articleBuilder = new ArticleBuilder($inlineQuery);
        $article = $articleBuilder->build();

        try {
            $this->bot->answerInlineQuery($inlineQuery->id, [$article]);
        } catch (Exception $e) {
            Logger::logApp("Failed to answer inline query $inlineQuery->id: {$e->getMessage()}");
        }
    }
}