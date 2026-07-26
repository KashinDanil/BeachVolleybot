<?php

declare(strict_types=1);

namespace BeachVolleybot\Validator\Rules;

use BeachVolleybot\Errors\ValidationError;
use BeachVolleybot\Weather\Location\KnownVenues;

readonly class KnownVenueRule implements RuleInterface
{
    public const string ERROR_MESSAGE = 'Selected venue is not a known venue';

    public function __construct(private ?string $venueName)
    {
    }

    public function isValid(): bool
    {
        return null !== $this->venueName && null !== KnownVenues::findByName($this->venueName);
    }

    public function getError(): ValidationError
    {
        return new ValidationError(self::ERROR_MESSAGE, ['venue' => $this->venueName]);
    }
}
