<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

readonly class Player implements PlayerInterface
{
    private const string PROFILE_URL_PREFIX = 'https://t.me/';

    public function __construct(
        private string $number,
        private string $name,
        private ?string $link,
        private int $volleyball,
        private int $net,
        private ?string $time,
    ) {
    }

    public static function buildName(string $firstName, ?string $lastName): string
    {
        return trim($firstName . ' ' . ($lastName ?? ''));
    }

    public static function buildLink(?string $username): ?string
    {
        if (null === $username) {
            return null;
        }

        return self::PROFILE_URL_PREFIX . $username;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getVolleyball(): int
    {
        return $this->volleyball;
    }

    public function getNet(): int
    {
        return $this->net;
    }

    public function getTime(): ?string
    {
        return $this->time;
    }
}
