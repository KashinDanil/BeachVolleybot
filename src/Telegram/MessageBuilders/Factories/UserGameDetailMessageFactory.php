<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameRecord;
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

    public static function build(GameRecord $gameRecord, int $listPage, Translator $translator): TelegramMessage
    {
        $game = GameFactory::fromRecord($gameRecord);
        $gameId = $gameRecord->gameId;

        $builder = $game->telegramMessageBuilder;
        // Mirrors the inline-share gate (`GameNotFinishedRule`): share stays available
        // until the kickoff day is over, not just until the kickoff hour.
        $sharingEnabled = !GameDateTimeResolver::isKickoffDayPast($game->getKickoffAt());

        self::prependSection($builder, self::buildLeadingSection($builder, $gameId, $translator, $sharingEnabled));
        self::overrideKeyboard($builder, $gameId, $listPage, $translator, $sharingEnabled);

        return $game->buildTelegramMessage();
    }

    private static function buildLeadingSection(
        GameMessageBuilder $builder,
        int $gameId,
        Translator $translator,
        bool $sharingEnabled,
    ): string {
        $header = self::buildHeader($builder, $gameId, $translator);

        if ($sharingEnabled) {
            return $header;
        }

        $formatter = $builder->getFormatter();
        $newLine = $formatter->newLine();

        // Single newline glues the notice to the header; trailing newline stacks on
        // top of the section separator (`\n\n`) for an extra gap before the body.
        return $header . $newLine . ShareGameMessageBuilder::renderDisabledNotice($formatter, $translator) . $newLine;
    }

    private static function buildHeader(GameMessageBuilder $builder, int $gameId, Translator $translator): string
    {
        return $builder->getFormatter()->bold(
            sprintf($translator->translate(self::HEADER_FORMAT), $gameId),
        );
    }

    private static function prependSection(GameMessageBuilder $builder, string $section): void
    {
        $previousSections = $builder->getEffective('getSections');

        $builder->override(
            'getSections',
            static function (GameInterface $game) use ($previousSections, $section): array {
                return [$section, ...$previousSections($game)];
            },
        );
    }

    private static function overrideKeyboard(
        GameMessageBuilder $builder,
        int $gameId,
        int $listPage,
        Translator $translator,
        bool $sharingEnabled,
    ): void {
        $rows = [];

        if ($sharingEnabled) {
            $rows[] = [$builder->buildSwitchInlineQueryButton(
                $translator->translate(ShareGameMessageBuilder::BUTTON_TEXT),
                ShareGameMessageBuilder::switchQuery($gameId),
            )];
        }

        $rows[] = [$builder->buildActionButton(
            $translator->translate(AbstractMessageBuilder::LABEL_BACK),
            UserCallbackData::create(UserCallbackAction::GamesList)->withPage($listPage),
        )];

        $builder->override('buildKeyboard', static fn(): array => $rows);
    }
}
