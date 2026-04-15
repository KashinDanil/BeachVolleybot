<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BadMethodCallException;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyleEnum;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use Closure;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

/**
 * @method array   buildActionButton(string $text, CallbackDataInterface $callbackData, ?InlineButtonStyleEnum $style = null)
 * @method array   buildButton(string $text, string $callbackData, ?InlineButtonStyleEnum $style = null)
 */
abstract class AbstractMessageBuilder
{
    protected const bool DISABLE_PREVIEW = true;

    /** @var array<string, Closure> */
    private array $overrides = [];

    public function __construct(
        protected readonly MessageFormatterInterface $formatter = new MarkdownV2(),
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

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist', static::class, $name));
    }

    protected function buildMessage(string $text, array $keyboard): TelegramMessage
    {
        return new TelegramMessage(
            new Text($text, $this->formatter->parseMode(), self::DISABLE_PREVIEW),
            new InlineKeyboardMarkup($keyboard),
        );
    }

    protected function defaultBuildActionButton(string $text, CallbackDataInterface $callbackData, ?InlineButtonStyleEnum $style = null): array
    {
        return $this->buildButton($text, $callbackData->toJson(), $style);
    }

    protected function defaultBuildButton(string $text, string $callbackData, ?InlineButtonStyleEnum $style = null): array
    {
        $button = ['text' => $text, 'callback_data' => $callbackData];
        if (null !== $style) {
            $button['style'] = $style->value;
        }

        return $button;
    }
}
