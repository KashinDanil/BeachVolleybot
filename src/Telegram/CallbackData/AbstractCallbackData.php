<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use JsonException;

abstract readonly class AbstractCallbackData implements CallbackDataInterface
{
    public static function fromJson(?string $json): ?static
    {
        if (null === $json) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $rawAction = $data[static::actionKey()] ?? null;
        if (!is_string($rawAction)) {
            return null;
        }

        $action = static::parseAction($rawAction);
        if (null === $action) {
            return null;
        }

        return static::fromData($action, $data);
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }

    abstract protected static function actionKey(): string;

    abstract protected static function parseAction(string $rawAction): ?CallbackActionInterface;

    /**
     * @param array<string, mixed> $data
     */
    abstract protected static function fromData(CallbackActionInterface $action, array $data): static;
}
