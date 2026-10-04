<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\HoursPhrase;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\User\NotificationType;

/** A type's texts in the reader's language, with every placeholder but the kickoff filled in. */
final readonly class LocalizedNotificationTexts
{
    private NotificationTypeTexts $texts;

    public function __construct(
        NotificationType $type,
        private Translator $translator,
    ) {
        $this->texts = NotificationTypeTexts::forType($type);
    }

    public function label(): string
    {
        return $this->translator->translate($this->texts->label);
    }

    public function trigger(): string
    {
        return sprintf($this->translator->translate($this->texts->trigger), ...$this->leadTimeArguments());
    }

    public function description(string $kickoffDay, string $kickoffTime): string
    {
        return sprintf(
            $this->translator->translate($this->texts->descriptionFormat),
            $kickoffDay,
            $kickoffTime,
            ...$this->leadTimeArguments(),
        );
    }

    public function hint(): ?string
    {
        if (null === $this->texts->hint) {
            return null;
        }

        return $this->translator->translate($this->texts->hint);
    }

    /** @return list<string> */
    private function leadTimeArguments(): array
    {
        if (null === $this->texts->leadTimeHours) {
            return [];
        }

        return [new HoursPhrase($this->texts->leadTimeHours, $this->translator)->text()];
    }
}
