<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

readonly class DefaultTelegramMessageBuilder implements TelegramMessageBuilderInterface
{
    public const string  VOLLEYBALL_EMOJI        = '🏐';
    public const string  NET_EMOJI               = '🕸️';
    private const string SEPARATOR               = "\n\n";
    private const int    EMOJI_COMPACT_THRESHOLD = 3;
    private const string PARSE_MODE              = 'Markdown';

    //Shortcuts are used as callback_data is limited to 64 bytes
    public const string KEY_ACTION          = 'a';
    public const string KEY_INLINE_QUERY_ID = 'q';

    public function build(GameInterface $game): TelegramMessage
    {
        return new TelegramMessage(
            new Text($this->buildText($game), self::PARSE_MODE),
            new InlineKeyboardMarkup($this->buildKeyboard($game)),
        );
    }

    private function buildKeyboard(GameInterface $game): array
    {
        return [
            [ // The first button is the meta-button — it carries the inline query ID, needed when a callback arrives on an inline message
                $this->buildButton('Sign Out', $this->buildCallbackData(CallbackAction::SignOut, $game->getInlineQueryId())),
                $this->buildButton('Sign Up', $this->buildCallbackData(CallbackAction::SignUp)),
            ],
            [
                $this->buildButton('-' . self::VOLLEYBALL_EMOJI, $this->buildCallbackData(CallbackAction::RemoveVolleyball)),
                $this->buildButton('+' . self::VOLLEYBALL_EMOJI, $this->buildCallbackData(CallbackAction::AddVolleyball)),
            ],
            [
                $this->buildButton('-' . self::NET_EMOJI, $this->buildCallbackData(CallbackAction::RemoveNet)),
                $this->buildButton('+' . self::NET_EMOJI, $this->buildCallbackData(CallbackAction::AddNet)),
            ],
        ];
    }

    private function buildButton(string $text, string $callbackData): array
    {
        return ['text' => $text, 'callback_data' => $callbackData];
    }

    private function buildCallbackData(CallbackAction $action, ?string $inlineQueryId = null): string
    {
        $payload = [self::KEY_ACTION => $action->value];

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
        $parts = [
            $player->getNumber() . '.',
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
