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
    public const string HEADER_FORMAT           = 'Game #%d';
    public const string SHARING_DISABLED_NOTICE = '🏁 This game has finished and can no longer be shared';

    public static function build(int $gameId, int $listPage, Translator $translator): ?TelegramMessage
    {
        $game = GameFactory::tryFromGameId($gameId);

        if (null === $game) {
            return null;
        }

        $builder = $game->telegramMessageBuilder;
        $isKickoffPast = GameDateTimeResolver::isKickoffPast($game->getTitle(), $game->getCreatedAt());

        self::prependSection($builder, self::buildLeadingSection($builder, $gameId, $translator, $isKickoffPast));
        self::overrideKeyboard($builder, $gameId, $listPage, $translator, $isKickoffPast);

        return $game->buildTelegramMessage();
    }

    private static function buildLeadingSection(
        GameMessageBuilder $builder,
        int $gameId,
        Translator $translator,
        bool $isKickoffPast,
    ): string {
        $header = self::buildHeader($builder, $gameId, $translator);

        if (!$isKickoffPast) {
            return $header;
        }

        $newLine = $builder->getFormatter()->newLine();

        // Single newline glues the notice to the header; trailing newline stacks on
        // top of the section separator (`\n\n`) for an extra gap before the body.
        return $header . $newLine . self::buildSharingDisabledNotice($builder, $translator) . $newLine;
    }

    private static function buildHeader(GameMessageBuilder $builder, int $gameId, Translator $translator): string
    {
        return $builder->getFormatter()->bold(
            sprintf($translator->translate(self::HEADER_FORMAT), $gameId),
        );
    }

    private static function buildSharingDisabledNotice(GameMessageBuilder $builder, Translator $translator): string
    {
        $formatter = $builder->getFormatter();

        return $formatter->blockquote(
            $formatter->escape($translator->translate(self::SHARING_DISABLED_NOTICE)),
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
        bool $isKickoffPast,
    ): void {
        $rows = [];

        if (!$isKickoffPast) {
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
