<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\MessageBuilders;

use BeachVolleybot\Game\GameInterface;
use BeachVolleybot\Game\PlayerInterface;
use BeachVolleybot\Telegram\Outgoing\TelegramMessage;

readonly class DefaultMessageBuilder implements MessageBuilderInterface
{
    public const string  BALL_EMOJI              = '🏐';
    public const string  NET_EMOJI               = '🕸️';
    private const string SEPARATOR               = "\n\n";
    private const int    EMOJI_COMPACT_THRESHOLD = 3;

    //Shortcuts are used as callback_data is limited to 64 bytes
    private const string KEY_ACTION  = 'a';
    private const string KEY_INLINE_QUERY_ID = 'q';

    private const string ACTION_ADD_PLAYER    = 'ap';
    private const string ACTION_REMOVE_PLAYER = 'rp';
    private const string ACTION_ADD_BALL      = 'ab';
    private const string ACTION_REMOVE_BALL   = 'rb';
    private const string ACTION_ADD_NET       = 'an';
    private const string ACTION_REMOVE_NET    = 'rn';

    public function build(GameInterface $game): TelegramMessage
    {
        return new TelegramMessage(
            $this->buildText($game),
            $this->buildKeyboard($game)
        );
    }

    private function buildKeyboard(GameInterface $game): array
    {
        return [
            [ // The first button is the meta-button — it carries the inline query ID, needed when a callback arrives on an inline message
                $this->buildButton('Sign Out', $this->buildCallbackData(self::ACTION_REMOVE_PLAYER, $game->getInlineQueryId())),
                $this->buildButton('Sign Up', $this->buildCallbackData(self::ACTION_ADD_PLAYER)),
            ],
            [
                $this->buildButton('-' . self::BALL_EMOJI, $this->buildCallbackData(self::ACTION_REMOVE_BALL)),
                $this->buildButton('+' . self::BALL_EMOJI, $this->buildCallbackData(self::ACTION_ADD_BALL)),
            ],
            [
                $this->buildButton('-' . self::NET_EMOJI, $this->buildCallbackData(self::ACTION_REMOVE_NET)),
                $this->buildButton('+' . self::NET_EMOJI, $this->buildCallbackData(self::ACTION_ADD_NET)),
            ],
        ];
    }

    private function buildButton(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    private function buildCallbackData(string $action, ?string $inlineQueryId = null): string
    {
        $payload = [self::KEY_ACTION => $action];

        if (null !== $inlineQueryId) {
            $payload[self::KEY_INLINE_QUERY_ID] = $inlineQueryId;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function buildText(GameInterface $game): string
    {
        $sections = array_filter([
            $game->getTitle(),
            $this->buildPlayerList($game),
            $game->getFooter(),
        ]);

        return implode(self::SEPARATOR, $sections);
    }

    private function buildPlayerList(GameInterface $game): string
    {
        $lines = [];
        $appearances = [];

        foreach ($game->getPlayers() as $player) {
            $key = $this->playerKey($player);
            $appearances[$key] = ($appearances[$key] ?? 0) + 1;

            $lines[] = $this->buildPlayerLine($player, $appearances[$key], $game->getTime());
        }

        return implode("\n", $lines);
    }

    private function buildPlayerLine(PlayerInterface $player, int $appearance, string $gameTime): string
    {
        $parts = array_filter([
            $player->getNumber() . '.',
            $this->displayName($player, $appearance),
            $this->formatEmoji($player->getBall(), self::BALL_EMOJI),
            $this->formatEmoji($player->getNet(), self::NET_EMOJI),
            $this->displayTime($player->getTime(), $gameTime),
        ]);

        return implode(' ', $parts);
    }

    private function displayName(PlayerInterface $player, int $appearance): string
    {
        $name = $player->getName();
        $link = $player->getLink();

        $formatted = null !== $link
            ? '[' . $name . '](' . $link . ')'
            : $name;

        if (1 < $appearance) {
            return $formatted . "'s +" . ($appearance - 1);
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
}