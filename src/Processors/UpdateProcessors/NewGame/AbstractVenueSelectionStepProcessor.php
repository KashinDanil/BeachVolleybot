<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\NewGame;

use BeachVolleybot\Telegram\MessageBuilders\Helpers\PlayersPerNetSelection;
use BeachVolleybot\Validator\Rules\DateTime\KickoffDayInTheFutureRule;
use BeachVolleybot\Validator\Rules\DateTime\ResolvableDateRule;
use BeachVolleybot\Validator\Rules\DateTime\ResolvableTimeRule;
use BeachVolleybot\Validator\Rules\Game\KnownVenueRule;
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

    protected function preserveCurrentState(?string $text): PlayersPerNetSelection
    {
        $appliedInText = $this->parsePlayersPerNet($text);
        $value = $this->callbackData->getPlayersPerNet() ?? $appliedInText ?? PlayersPerNetSelection::DEFAULT;

        return null !== $appliedInText
            ? PlayersPerNetSelection::applied($value)
            : PlayersPerNetSelection::pending($value);
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
