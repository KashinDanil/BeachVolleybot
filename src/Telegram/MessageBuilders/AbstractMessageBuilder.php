<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BadMethodCallException;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\CallbackData\PageableCallbackDataInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use Closure;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Inline\InputMessageContent\Text;

/**
 * @method array   buildActionButton(string $text, CallbackDataInterface $callbackData, ?InlineButtonStyle $style = null)
 * @method array   buildButton(string $text, string $callbackData, ?InlineButtonStyle $style = null)
 * @method array   buildSwitchInlineQueryButton(string $text, string $query)
 */
abstract class AbstractMessageBuilder
{
    protected const bool   DISABLE_PREVIEW = true;
    public const string    LABEL_PREVIOUS  = '« Prev';
    public const string    LABEL_NEXT      = 'Next »';
    public const string    LABEL_BACK      = '↩ Back';

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

    public function getFormatter(): MessageFormatterInterface
    {
        return $this->formatter;
    }

    public function getEffective(string $method): Closure
    {
        if (isset($this->overrides[$method])) {
            return $this->overrides[$method];
        }

        $default = $this->resolveDefault($method);
        if (null !== $default) {
            return $default;
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist', static::class, $method));
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (isset($this->overrides[$name])) {
            return ($this->overrides[$name])(...$arguments);
        }

        $default = $this->resolveDefault($name);
        if (null !== $default) {
            return $default(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist', static::class, $name));
    }

    private function resolveDefault(string $method): ?Closure
    {
        $default = 'default' . ucfirst($method);
        if (!method_exists($this, $default)) {
            return null;
        }

        return fn(...$arguments) => $this->$default(...$arguments);
    }

    protected function buildMessage(string $text, array $keyboard): TelegramMessage
    {
        return new TelegramMessage(
            new Text($text, $this->formatter->parseMode(), self::DISABLE_PREVIEW),
            new InlineKeyboardMarkup($keyboard),
        );
    }

    protected function defaultBuildActionButton(string $text, CallbackDataInterface $callbackData, ?InlineButtonStyle $style = null): array
    {
        return $this->buildButton($text, $callbackData->toJson(), $style);
    }

    protected function defaultBuildButton(string $text, string $callbackData, ?InlineButtonStyle $style = null): array
    {
        $button = ['text' => $text, 'callback_data' => $callbackData];
        if (null !== $style) {
            $button['style'] = $style->value;
        }

        return $button;
    }

    protected function defaultBuildSwitchInlineQueryButton(string $text, string $query): array
    {
        return ['text' => $text, 'switch_inline_query' => $query];
    }

    /** @return list<array{text: string, callback_data: string}> */
    protected function backButtonRow(
        CallbackDataInterface $callbackData,
        string $label = self::LABEL_BACK,
    ): array {
        return [$this->buildActionButton($label, $callbackData)];
    }

    /** @return ?list<array{text: string, callback_data: string}> */
    protected function paginationRow(
        KeyboardPagination $pagination,
        PageableCallbackDataInterface $callbackData,
        string $previousLabel = self::LABEL_PREVIOUS,
        string $nextLabel = self::LABEL_NEXT,
    ): ?array {
        $row = [];
        $previousPage = $pagination->getPreviousPage();
        $nextPage = $pagination->getNextPage();

        if (null !== $previousPage) {
            $row[] = $this->buildActionButton($previousLabel, $callbackData->withPage($previousPage));
        }

        if (null !== $nextPage) {
            $row[] = $this->buildActionButton($nextLabel, $callbackData->withPage($nextPage));
        }

        if ([] === $row) {
            return null;
        }

        return $row;
    }
}
