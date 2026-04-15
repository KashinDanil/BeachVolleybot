<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyleEnum;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\GameWarningCollector;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

/**
 * @method string  separator()
 * @method string  buildText(GameInterface $game)
 * @method string  buildTitle(GameInterface $game)
 * @method string  buildPlayerList(GameInterface $game)
 * @method string  buildPlayerLine(PlayerInterface $player, int $appearance, string $gameTime)
 * @method string  displayName(PlayerInterface $player, int $appearance)
 * @method int     plusCount(PlayerInterface $player, int $appearance)
 * @method string  displayTime(?string $playerTime, string $gameTime)
 * @method string|null buildLocationLink(?string $location)
 * @method string|null buildWarning(array $players)
 * @method string  playerKey(PlayerInterface $player)
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
        $sections = array_filter([
            $this->buildWarning($game->getPlayers()),
            $this->buildTitle($game),
            $this->buildPlayerList($game),
            $this->buildLocationLink($game->getLocation()),
        ]);

        return implode($this->separator(), $sections);
    }

    /** @param PlayerInterface[] $players */
    protected function defaultBuildWarning(array $players): ?string
    {
        if (empty($players)) {
            return null;
        }

        $messages = $this->warningCollector->collect($players);

        if (empty($messages)) {
            return null;
        }

        return $this->formatter->blockquote('⚠️ ' . implode($this->formatter->newLine(), $messages)) . $this->formatter->newLine();
    }

    protected function defaultBuildTitle(GameInterface $game): string
    {
        return $this->formatter->escape($game->getTitle());
    }

    protected function defaultBuildPlayerList(GameInterface $game): string
    {
        $lines = [];
        $appearances = [];

        $gameTime = $game->getTime();
        foreach ($game->getPlayers() as $player) {
            $key = $this->playerKey($player);
            $appearances[$key] = ($appearances[$key] ?? 0) + 1;

            $lines[] = $this->buildPlayerLine($player, $appearances[$key], $gameTime);
        }

        return implode($this->formatter->newLine(), $lines);
    }

    protected function defaultBuildPlayerLine(PlayerInterface $player, int $appearance, string $gameTime): string
    {
        $parts = [
            $this->formatter->escape($player->getNumber() . '.'),
            $this->displayName($player, $appearance),
        ];

        if (1 === $appearance) {
            $parts[] = $this->formatEmoji($player->getVolleyball(), self::VOLLEYBALL_EMOJI);
            $parts[] = $this->formatEmoji($player->getNet(), self::NET_EMOJI);
        }

        $parts[] = $this->displayTime($player->getTime(), $gameTime);

        return implode(' ', array_filter($parts));
    }

    protected function defaultDisplayName(PlayerInterface $player, int $appearance): string
    {
        $name = $player->getName();
        $link = $player->getLink();

        $formatted = null !== $link
            ? $this->formatter->link($name, $link)
            : $this->formatter->escape($name);

        if (1 < $appearance) {
            $plusCount = $this->plusCount($player, $appearance);

            return $this->formatter->escape('+' . $plusCount . ' (') . $formatted . $this->formatter->escape(')');
        }

        return $formatted;
    }

    protected function defaultPlusCount(PlayerInterface $player, int $appearance): int
    {
        return $appearance - 1;
    }

    protected function defaultDisplayTime(?string $playerTime, string $gameTime): string
    {
        if (null === $playerTime || $playerTime === $gameTime) {
            return '';
        }

        return $this->formatter->escape($playerTime);
    }

    protected function defaultBuildLocationLink(?string $location): ?string
    {
        if (null === $location) {
            return null;
        }

        return $this->formatter->link('📍 Location', 'https://maps.google.com/?q=' . $location);
    }

    protected function defaultPlayerKey(PlayerInterface $player): string
    {
        return $player->getName() . "\0" . ($player->getLink() ?? '');
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
            [ // The first button is the meta-button — it carries the inline query ID
                $this->buildButton('Leave', CallbackData::encode(CallbackAction::Leave, $game->getInlineQueryId()), InlineButtonStyleEnum::DANGER),
                $this->buildButton('Join', CallbackData::encode(CallbackAction::Join), InlineButtonStyleEnum::SUCCESS),
            ],
            [
                $this->buildButton('-' . self::VOLLEYBALL_EMOJI, CallbackData::encode(CallbackAction::RemoveVolleyball)),
                $this->buildButton('+' . self::VOLLEYBALL_EMOJI, CallbackData::encode(CallbackAction::AddVolleyball)),
            ],
            [
                $this->buildButton('-' . self::NET_EMOJI, CallbackData::encode(CallbackAction::RemoveNet)),
                $this->buildButton('+' . self::NET_EMOJI, CallbackData::encode(CallbackAction::AddNet)),
            ],
        ];
    }
}
