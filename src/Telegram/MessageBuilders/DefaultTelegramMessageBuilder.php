<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\CallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

readonly class DefaultTelegramMessageBuilder implements TelegramMessageBuilderInterface
{
    private const string VOLLEYBALL_EMOJI        = '🏐';
    private const string NET_EMOJI               = '🕸️';
    private const string SEPARATOR               = "\n\n";
    private const int    EMOJI_COMPACT_THRESHOLD = 3;
    private const bool   DISABLE_PREVIEW         = true;

    public function __construct(
        protected MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
    }

    public function build(GameInterface $game): TelegramMessage
    {
        return new TelegramMessage(
            new Text($this->buildText($game), $this->formatter->parseMode(), self::DISABLE_PREVIEW),
            new InlineKeyboardMarkup($this->buildKeyboard($game)),
        );
    }

    private function buildText(GameInterface $game): string
    {
        $sections = array_filter([
            $this->formatter->escape($game->getTitle()),
            $this->buildPlayerList($game),
            $this->buildLocationLink($game->getLocation()),
        ]);

        return implode(self::SEPARATOR, $sections);
    }

    private function buildPlayerList(GameInterface $game): string
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

    private function buildPlayerLine(PlayerInterface $player, int $appearance, string $gameTime): string
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

    private function displayName(PlayerInterface $player, int $appearance): string
    {
        $name = $player->getName();
        $link = $player->getLink();

        $formatted = null !== $link
            ? $this->formatter->link($name, $link)
            : $this->formatter->escape($name);

        if (1 < $appearance) {
            return $formatted . $this->formatter->escape("'s +" . ($appearance - 1));
        }

        return $formatted;
    }

    private function displayTime(?string $playerTime, string $gameTime): string
    {
        if (null === $playerTime || $playerTime === $gameTime) {
            return '';
        }

        return $playerTime;
    }

    private function buildLocationLink(?string $location): ?string
    {
        if (null === $location) {
            return null;
        }

        return $this->formatter->link('📍 Location', 'https://maps.google.com/?q=' . $location);
    }

    private function playerKey(PlayerInterface $player): string
    {
        return $player->getName() . "\0" . ($player->getLink() ?? '');
    }

    private function formatEmoji(int $count, string $emoji): string
    {
        return match (true) {
            0 === $count => '',
            $count < self::EMOJI_COMPACT_THRESHOLD => str_repeat($emoji, $count),
            default => $emoji . 'x' . $count,
        };
    }

    // --- Keyboard ---

    private function buildKeyboard(GameInterface $game): array
    {
        return [
            [ // The first button is the meta-button — it carries the inline query ID
                $this->buildButton('Leave', CallbackData::encode(CallbackAction::Leave, $game->getInlineQueryId())),
                $this->buildButton('Join', CallbackData::encode(CallbackAction::Join)),
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

    private function buildButton(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }
}