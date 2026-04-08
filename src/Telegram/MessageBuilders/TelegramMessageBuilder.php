<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\CallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyleEnum;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BadMethodCallException;
use Closure;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

/**
 * @method string  buildText(GameInterface $game)
 * @method string  buildTitle(GameInterface $game)
 * @method string  buildPlayerList(GameInterface $game)
 * @method string  buildPlayerLine(PlayerInterface $player, int $appearance, string $gameTime)
 * @method string  displayName(PlayerInterface $player, int $appearance)
 * @method int     plusCount(PlayerInterface $player, int $appearance)
 * @method string  displayTime(?string $playerTime, string $gameTime)
 * @method ?string buildLocationLink(?string $location)
 * @method string  playerKey(PlayerInterface $player)
 * @method string  formatEmoji(int $count, string $emoji)
 * @method array   buildKeyboard(GameInterface $game)
 * @method array   buildButton(string $text, string $callbackData, ?InlineButtonStyleEnum $style = null)
 */
final class TelegramMessageBuilder
{
    private const string VOLLEYBALL_EMOJI        = '🏐';
    private const string NET_EMOJI               = '🕸️';
    private const string SEPARATOR               = "\n\n";
    private const int    EMOJI_COMPACT_THRESHOLD = 3;
    private const bool   DISABLE_PREVIEW         = true;

    /** @var array<string, Closure> */
    private array $overrides = [];

    public function __construct(
        private readonly MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
    }

    public function override(string $method, Closure $override): void
    {
        $this->overrides[$method] = $override;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (isset($this->overrides[$name])) {
            return ($this->overrides[$name])(...$arguments);
        }

        $default = 'default' . ucfirst($name);
        if (method_exists($this, $default)) {
            return $this->$default(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist', self::class, $name));
    }

    public function build(GameInterface $game): TelegramMessage
    {
        return new TelegramMessage(
            new Text($this->buildText($game), $this->formatter->parseMode(), self::DISABLE_PREVIEW),
            new InlineKeyboardMarkup($this->buildKeyboard($game)),
        );
    }

    private function defaultBuildText(GameInterface $game): string
    {
        $sections = array_filter([
            $this->buildTitle($game),
            $this->buildPlayerList($game),
            $this->buildLocationLink($game->getLocation()),
        ]);

        return implode(self::SEPARATOR, $sections);
    }

    private function defaultBuildTitle(GameInterface $game): string
    {
        return $this->formatter->escape($game->getTitle());
    }

    private function defaultBuildPlayerList(GameInterface $game): string
    {
        $lines = [];
        $appearances = [];

        $gameTime = $game->getTime();
        foreach ($game->getPlayers() as $player) {
            $key = $this->playerKey($player);
            $appearances[$key] = ($appearances[$key] ?? 0) + 1;

            $lines[] = $this->buildPlayerLine($player, $appearances[$key], $gameTime);
        }

        return implode("\n", $lines);
    }

    private function defaultBuildPlayerLine(PlayerInterface $player, int $appearance, string $gameTime): string
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

    private function defaultDisplayName(PlayerInterface $player, int $appearance): string
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

    private function defaultPlusCount(PlayerInterface $player, int $appearance): int
    {
        return $appearance - 1;
    }

    private function defaultDisplayTime(?string $playerTime, string $gameTime): string
    {
        if (null === $playerTime || $playerTime === $gameTime) {
            return '';
        }

        return $this->formatter->escape($playerTime);
    }

    private function defaultBuildLocationLink(?string $location): ?string
    {
        if (null === $location) {
            return null;
        }

        return $this->formatter->link('📍 Location', 'https://maps.google.com/?q=' . $location);
    }

    private function defaultPlayerKey(PlayerInterface $player): string
    {
        return $player->getName() . "\0" . ($player->getLink() ?? '');
    }

    private function defaultFormatEmoji(int $count, string $emoji): string
    {
        return match (true) {
            0 === $count => '',
            $count < self::EMOJI_COMPACT_THRESHOLD => str_repeat($emoji, $count),
            default => $emoji . '×' . $count,
        };
    }

    // --- Keyboard ---

    private function defaultBuildKeyboard(GameInterface $game): array
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

    private function defaultBuildButton(string $text, string $callbackData, ?InlineButtonStyleEnum $style = null): array
    {
        $button = ['text' => $text, 'callback_data' => $callbackData];
        if (null !== $style) {
            $button['style'] = $style->value;
        }

        return $button;
    }
}
