<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Game;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Game\Roster\Lineup;
use BeachVolleybot\Game\Roster\PlayerLimit;
use BeachVolleybot\Localization\TitleLanguageResolver;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\GameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\AbstractMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\GameWarningCollector;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

/**
 * @method string  separator()
 * @method string  buildText(GameInterface $game, Translator $translator)
 * @method list<?string> getSections(GameInterface $game, Translator $translator)
 * @method string  buildTitle(GameInterface $game)
 * @method string  buildUserList(GameInterface $game)
 * @method string  buildUserLine(UserInterface $user, int $appearance, string $gameTime)
 * @method string  displayName(UserInterface $user, int $appearance)
 * @method int     plusCount(UserInterface $user, int $appearance)
 * @method string  displayTime(string $userTime, string $gameTime)
 * @method string|null buildLocationLink(?string $location, Translator $translator)
 * @method string|null buildWarning(GameInterface $game, Translator $translator)
 * @method string  userKey(UserInterface $user)
 * @method string  formatEmoji(int $count, string $emoji)
 * @method array   buildKeyboard(GameInterface $game, Translator $translator)
 */
final class GameMessageBuilder extends AbstractMessageBuilder
{
    private const string VOLLEYBALL_EMOJI        = '🏐';
    private const string NET_EMOJI               = '🕸️';
    private const int    EMOJI_COMPACT_THRESHOLD = 3;
    private const string RESERVES_DIVIDER        = '———';

    public const string LABEL_JOIN  = 'Join (+1)';
    public const string LABEL_LEAVE = 'Leave (−1)';

    public function __construct(
        MessageFormatterInterface $formatter = new MarkdownV2(),
        private readonly GameWarningCollector $warningCollector = new GameWarningCollector(
            new NoEquipmentWarning(),
        ),
    ) {
        parent::__construct($formatter);
    }

    public function build(GameInterface $game): TelegramMessage
    {
        $translator = new Translator(TitleLanguageResolver::resolve($game->getTitle()));

        return $this->buildMessage($this->buildText($game, $translator), $this->buildKeyboard($game, $translator));
    }

    protected function defaultSeparator(): string
    {
        return $this->formatter->newLine() . $this->formatter->newLine();
    }

    protected function defaultBuildText(GameInterface $game, Translator $translator): string
    {
        return implode($this->separator(), array_filter($this->getSections($game, $translator)));
    }

    /** @return list<?string> */
    protected function defaultGetSections(GameInterface $game, Translator $translator): array
    {
        return [
            $this->buildWarning($game, $translator),
            $this->buildTitle($game),
            $this->buildUserList($game),
            $this->buildLocationLink($game->getLocation(), $translator),
        ];
    }

    protected function defaultBuildWarning(GameInterface $game, Translator $translator): ?string
    {
        $messages = $this->warningCollector->collect($game, $translator);

        if (empty($messages)) {
            return null;
        }

        $warningText = $this->formatter->escape('⚠️ ' . implode($this->formatter->newLine(), $messages));

        return $this->formatter->blockquote($warningText) . $this->formatter->newLine();
    }

    protected function defaultBuildTitle(GameInterface $game): string
    {
        return $this->formatter->escape($game->getTitle());
    }

    protected function defaultBuildUserList(GameInterface $game): string
    {
        $lines = [];
        $appearances = [];
        $limit = PlayerLimit::resolveLimit($game->getUsers(), $game->getSettings());
        $dividerEmitted = false;

        $gameTime = $game->getTime();
        foreach (new Lineup($game->getUsers(), $limit)->getRowsToRender() as $user) {
            if (!$dividerEmitted && !empty($lines) && $limit->isReserve($user)) {
                $lines[] = $this->formatter->escape(self::RESERVES_DIVIDER);
                $dividerEmitted = true;
            }

            $key = $this->userKey($user);
            $appearances[$key] = ($appearances[$key] ?? 0) + 1;

            $lines[] = $this->buildUserLine($user, $appearances[$key], $gameTime);
        }

        return implode($this->formatter->newLine(), $lines);
    }

    protected function defaultBuildUserLine(UserInterface $user, int $appearance, string $gameTime): string
    {
        $parts = [
            $this->formatter->escape($user->getPosition()->format() . '.'),
            $this->displayName($user, $appearance),
        ];

        if (1 === $appearance) {
            $parts[] = $this->formatEmoji($user->getVolleyball(), self::VOLLEYBALL_EMOJI);
            $parts[] = $this->formatEmoji($user->getNet(), self::NET_EMOJI);
        }

        $parts[] = $this->displayTime($user->getTime(), $gameTime);

        return implode(' ', array_filter($parts));
    }

    protected function defaultDisplayName(UserInterface $user, int $appearance): string
    {
        $name = $user->getName();
        $link = $user->getLink();

        $formatted = null !== $link
            ? $this->formatter->link($name, $link)
            : $this->formatter->escape($name);

        if (1 < $appearance) {
            $plusCount = $this->plusCount($user, $appearance);

            return $this->formatter->escape('+' . $plusCount . ' (') . $formatted . $this->formatter->escape(')');
        }

        return $formatted;
    }

    protected function defaultPlusCount(UserInterface $user, int $appearance): int
    {
        return $appearance - 1;
    }

    protected function defaultDisplayTime(string $userTime, string $gameTime): string
    {
        return $this->formatter->escape($userTime);
    }

    protected function defaultBuildLocationLink(?string $location, Translator $translator): ?string
    {
        if (null === $location) {
            return null;
        }

        return $this->formatter->link($translator->translate('📍 Location'), 'https://maps.google.com/?q=' . $location);
    }

    protected function defaultUserKey(UserInterface $user): string
    {
        return $user->getName() . "\0" . ($user->getLink() ?? '');
    }

    protected function defaultFormatEmoji(int $count, string $emoji): string
    {
        return match (true) {
            0 === $count => '',
            $count < self::EMOJI_COMPACT_THRESHOLD => str_repeat($emoji, $count),
            default => $emoji . '×' . $count,
        };
    }

    protected function defaultBuildKeyboard(GameInterface $game, Translator $translator): array
    {
        return [
            [ // The first button is the meta-button — it carries the game key
                $this->buildActionButton($translator->translate(self::LABEL_LEAVE), GameCallbackData::create(GameCallbackAction::Leave)->withGameKey($game->getGameKey()), InlineButtonStyle::DANGER),
                $this->buildActionButton($translator->translate(self::LABEL_JOIN), GameCallbackData::create(GameCallbackAction::Join), InlineButtonStyle::SUCCESS),
            ],
            [
                $this->buildActionButton('-' . self::VOLLEYBALL_EMOJI, GameCallbackData::create(GameCallbackAction::RemoveVolleyball)),
                $this->buildActionButton('+' . self::VOLLEYBALL_EMOJI, GameCallbackData::create(GameCallbackAction::AddVolleyball)),
            ],
            [
                $this->buildActionButton('-' . self::NET_EMOJI, GameCallbackData::create(GameCallbackAction::RemoveNet)),
                $this->buildActionButton('+' . self::NET_EMOJI, GameCallbackData::create(GameCallbackAction::AddNet)),
            ],
        ];
    }
}
