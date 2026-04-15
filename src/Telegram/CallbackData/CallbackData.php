<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;

final class CallbackData
{
    private const string KEY_ACTION          = 'a';
    private const string KEY_INLINE_QUERY_ID = 'q';

    public static function encode(CallbackAction $action, ?string $inlineQueryId = null): string
    {
        $payload = [self::KEY_ACTION => $action->value];

        if (null !== $inlineQueryId) {
            $payload[self::KEY_INLINE_QUERY_ID] = $inlineQueryId;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    public static function extractAction(?string $json): ?CallbackAction
    {
        if (null === $json) {
            return null;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return CallbackAction::tryFrom($data[self::KEY_ACTION] ?? '');
    }

    public static function extractInlineQueryId(TelegramMessage $replyToMessage): ?string
    {
        $metaButton = $replyToMessage->replyMarkup?->inlineKeyboard[0][0] ?? null;

        if (null === $metaButton) {
            return null;
        }

        if (null === $metaButton->callbackData) {
            return null;
        }

        $decoded = json_decode($metaButton->callbackData, true, 512, JSON_THROW_ON_ERROR);

        return $decoded[self::KEY_INLINE_QUERY_ID] ?? null;
    }
}
