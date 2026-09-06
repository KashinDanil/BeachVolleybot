<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramInlineQuery;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Outgoing\ArticleBuilderInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\ErrorArticleBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\ForwardGameArticleBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\InlineQueryError;
use BeachVolleybot\Telegram\Messages\Outgoing\NewGameArticleBuilder;
use BeachVolleybot\User\CurrentUser;
use BeachVolleybot\Validator\Rules\DateTimeInTitleRule;
use BeachVolleybot\Validator\Rules\GameCreatorOrAdminRule;
use BeachVolleybot\Validator\Rules\GameNotFinishedRule;
use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use BeachVolleybot\Validator\Rules\RuleInterface;
use BeachVolleybot\Validator\Validator;
use DateTimeImmutable;

class InlineQueryProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $inlineQuery = $update->inlineQuery;
        $this->logUserAction($inlineQuery->from, 'inline_query', $inlineQuery->query);
        $translator = Translator::fromUser($inlineQuery->from);

        $articleBuilder = $this->resolveArticleBuilder($inlineQuery, $translator);

        $article = $articleBuilder->build();
        $this->telegramSender->answerInlineQuery($inlineQuery->id, [$article]);
    }

    private function resolveArticleBuilder(TelegramInlineQuery $inlineQuery, Translator $translator): ArticleBuilderInterface
    {
        $forwardGameId = ForwardGameQueryExtractor::extract($inlineQuery->query);

        if (null !== $forwardGameId) {
            return $this->buildForwardArticleBuilder($inlineQuery, $translator, $forwardGameId);
        }

        return $this->buildNewGameArticleBuilder($inlineQuery, $translator);
    }

    private function buildForwardArticleBuilder(
        TelegramInlineQuery $inlineQuery,
        Translator $translator,
        int $gameId,
    ): ArticleBuilderInterface {
        $gameRecord = new GameManager()->findGameRecordById($gameId);

        if (null === $gameRecord) {
            return new ErrorArticleBuilder(InlineQueryError::gameNotFound(), $translator);
        }

        $currentUser = CurrentUser::fromTelegramId($inlineQuery->from->id);
        $validationState = new Validator(
            [
                new GameCreatorOrAdminRule(
                    $inlineQuery->from->id,
                    $gameRecord->createdBy,
                    $currentUser->isAdmin(),
                ),
                new GameNotFinishedRule($gameRecord->kickoffAt),
            ]
        )->validate();

        if ($validationState->isSuccess()) {
            return new ForwardGameArticleBuilder($inlineQuery, $gameId, $gameRecord->title, $translator);
        }

        return new ErrorArticleBuilder(
            InlineQueryError::fromForwardError($validationState->getError()),
            $translator,
        );
    }

    private function buildNewGameArticleBuilder(TelegramInlineQuery $inlineQuery, Translator $translator): ArticleBuilderInterface
    {
        $validationState = new Validator(self::newGameValidationRules($inlineQuery->query))->validate();

        if ($validationState->isSuccess()) {
            return new NewGameArticleBuilder($inlineQuery, $translator);
        }

        return new ErrorArticleBuilder(InlineQueryError::fromError($validationState->getError()), $translator);
    }

    /** @return list<RuleInterface> */
    public static function newGameValidationRules(string $query): array
    {
        return [
            new DateTimeInTitleRule($query),
            new KickoffDayInTheFutureRule($query, new DateTimeImmutable()),
        ];
    }
}
