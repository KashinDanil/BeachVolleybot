<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Outgoing\ArticleBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\ErrorArticleBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use BeachVolleybot\Validator\Rules\RuleInterface;
use BeachVolleybot\Validator\Rules\TimeInTitleRule;
use BeachVolleybot\Validator\Validator;

class InlineQueryProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $inlineQuery = $update->inlineQuery;
        $translator = Translator::fromUser($inlineQuery->from);

        $validationState = new Validator($this->validationRules($inlineQuery->query))->validate();

        if ($validationState->isSuccess()) {
            $articleBuilder = new ArticleBuilder($inlineQuery, $translator);
        } else {
            $inlineQueryError = InlineQueryError::fromError($validationState->getError());
            $articleBuilder = new ErrorArticleBuilder($inlineQueryError, $translator);
        }

        $article = $articleBuilder->build();
        $this->telegramSender->answerInlineQuery($inlineQuery->id, [$article]);
    }

    /** @return list<RuleInterface> */
    public function validationRules(string $query): array
    {
        return [
            new TimeInTitleRule($query),
        ];
    }
}
