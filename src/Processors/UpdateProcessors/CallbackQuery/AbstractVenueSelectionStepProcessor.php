<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use BeachVolleybot\Validator\Rules\KnownVenueRule;
use BeachVolleybot\Validator\Rules\ResolvableDateRule;
use BeachVolleybot\Validator\Rules\ResolvableTimeRule;
use BeachVolleybot\Validator\Rules\RuleInterface;
use BeachVolleybot\Weather\Location\KnownVenues;
use BeachVolleybot\Weather\Location\Venue;
use DateTimeImmutable;


abstract class AbstractVenueSelectionStepProcessor extends AbstractNewGameStepProcessor
{
    protected function resolveVenue(): ?Venue
    {
        $venueName = $this->callbackData->getVenueName();

        if (null === $venueName) {
            return null;
        }

        return KnownVenues::findByName($venueName);
    }

    /**
     * @return list<RuleInterface>
     */
    protected function selectionRules(?string $text): array
    {
        $rules = [
            new ResolvableDateRule($text, new DateTimeImmutable()),
            new ResolvableTimeRule($text),
            new KickoffDayInTheFutureRule($text, new DateTimeImmutable()),
        ];

        if (null !== $this->callbackData->getVenueName()) {
            $rules[] = new KnownVenueRule($this->callbackData->getVenueName());
        }

        return $rules;
    }
}
