<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\UserInterface;
use BeachVolleybot\Processors\UpdateProcessors\GameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\GameCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\GameWarningCollector;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

/**
 * @method string  separator()
 * @method string  buildText(GameInterface $game)
 * @method list<?string> getSections(GameInterface $game)
 * @method string  buildTitle(GameInterface $game)
 * @method string  buildUserList(GameInterface $game)
 * @method string  buildUserLine(UserInterface $user, int $appearance, string $gameTime)
 * @method string  displayName(UserInterface $user, int $appearance)
 * @method int     plusCount(UserInterface $user, int $appearance)
 * @method string  displayTime(string $userTime, string $gameTime)
 * @method string|null buildLocationLink(?string $location)
 * @method string|null buildWarning(array $users)
 * @method string  userKey(UserInterface $user)
 * @method string  formatEmoji(int $count, string $emoji)
 * @method array   buildKeyboard(GameInterface $game)
 */
final class GameMessageBuilder extends AbstractMessageBuilder
{
    private const string VOLLEYBALL_EMOJI        = '🏐';
    private const string NET_EMOJI               = '🕸️';
    private const int    EMOJI_COMPACT_THRESHOLD = 3;

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
        return $this->buildMessage($this->buildText($game), $this->buildKeyboard($game));
    }

    protected function defaultSeparator(): string
    {
        return $this->formatter->newLine() . $this->formatter->newLine();
    }

    protected function defaultBuildText(GameInterface $game): string
    {
        return implode($this->separator(), array_filter($this->getSections($game)));
    }

    /** @return list<?string> */
    protected function defaultGetSections(GameInterface $game): array
    {
        return [
            $this->buildWarning($game->getUsers()),
            $this->buildTitle($game),
            $this->buildUserList($game),
            $this->buildLocationLink($game->getLocation()),
        ];
    }

    /** @param UserInterface[] $users */
    protected function defaultBuildWarning(array $users): ?string
    {
        if (empty($users)) {
            return null;
        }

        $messages = $this->warningCollector->collect($users);

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

        $gameTime = $game->getTime();
        foreach ($game->getUsers() as $user) {
            $key = $this->userKey($user);
            $appearances[$key] = ($appearances[$key] ?? 0) + 1;

            $lines[] = $this->buildUserLine($user, $appearances[$key], $gameTime);
        }

        return implode($this->formatter->newLine(), $lines);
    }

    protected function defaultBuildUserLine(UserInterface $user, int $appearance, string $gameTime): string
    {
        $parts = [
            $this->formatter->escape($user->getNumber() . '.'),
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

    protected function defaultBuildLocationLink(?string $location): ?string
    {
        if (null === $location) {
            return null;
        }

        return $this->formatter->link('📍 Location', 'https://maps.google.com/?q=' . $location);
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

    protected function defaultBuildKeyboard(GameInterface $game): array
    {
        return [
            [ // The first button is the meta-button — it carries the game key
                $this->buildActionButton('Leave', GameCallbackData::create(GameCallbackAction::Leave)->withGameKey($game->getGameKey()), InlineButtonStyle::DANGER),
                $this->buildActionButton('Join', GameCallbackData::create(GameCallbackAction::Join), InlineButtonStyle::SUCCESS),
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
