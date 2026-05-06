<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\AbstractMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\GameMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\ShareGameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UserGameDetailMessageFactory
{
    public const string HEADER_FORMAT = 'Game #%d';

    public static function build(int $gameId, int $listPage, Translator $translator): ?TelegramMessage
    {
        $game = GameFactory::tryFromGameId($gameId);

        if (null === $game) {
            return null;
        }

        $builder = $game->telegramMessageBuilder;
        self::installHeaderOverride($builder, $gameId, $translator);
        self::installKeyboardOverride($builder, $gameId, $listPage, $translator);

        return $game->buildTelegramMessage();
    }

    private static function installHeaderOverride(GameMessageBuilder $builder, int $gameId, Translator $translator): void
    {
        $previousSections = $builder->getEffective('getSections');
        $formatter = $builder->getFormatter();
        $headerText = sprintf($translator->translate(self::HEADER_FORMAT), $gameId);

        $builder->override(
            'getSections',
            static function (GameInterface $game) use ($previousSections, $formatter, $headerText): array {
                return [$formatter->bold($headerText), ...$previousSections($game)];
            },
        );
    }

    private static function installKeyboardOverride(
        GameMessageBuilder $builder,
        int $gameId,
        int $listPage,
        Translator $translator,
    ): void {
        $shareLabel = $translator->translate(ShareGameMessageBuilder::BUTTON_TEXT);
        $backLabel = $translator->translate(AbstractMessageBuilder::LABEL_BACK);
        $shareQuery = ShareGameMessageBuilder::switchQuery($gameId);
        $backCallback = UserCallbackData::create(UserCallbackAction::GamesList)->withPage($listPage);

        $builder->override(
            'buildKeyboard',
            static function (GameInterface $game) use ($builder, $shareLabel, $shareQuery, $backLabel, $backCallback): array {
                $rows = [];

                if (!GameDateTimeResolver::isKickoffPast($game->getTitle(), $game->getCreatedAt())) {
                    $rows[] = [$builder->buildSwitchInlineQueryButton($shareLabel, $shareQuery)];
                }

                $rows[] = [$builder->buildActionButton($backLabel, $backCallback)];

                return $rows;
            },
        );
    }
}
