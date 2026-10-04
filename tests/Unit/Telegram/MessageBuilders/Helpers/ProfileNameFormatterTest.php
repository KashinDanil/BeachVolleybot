<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Helpers;

use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\ProfileNameFormatter;
use BeachVolleybot\Tests\Fixtures\CreatesUserRecords;
use PHPUnit\Framework\TestCase;

final class ProfileNameFormatterTest extends TestCase
{
    use CreatesUserRecords;

    private ProfileNameFormatter $profileNameFormatter;

    public function testEscapesANameWithoutALink(): void
    {
        $this->assertSame('Anna\\-Maria', $this->profileNameFormatter->format('Anna-Maria', null));
    }

    public function testLinksANameToItsProfile(): void
    {
        $this->assertSame(
            '[Anna\\-Maria](https://t.me/anna)',
            $this->profileNameFormatter->format('Anna-Maria', 'https://t.me/anna'),
        );
    }

    public function testLinksAUserWithAUsernameByFullName(): void
    {
        $user = $this->userRecord(firstName: 'Alice', lastName: 'Smith', username: 'alice');

        $this->assertSame('[Alice Smith](https://t.me/alice)', $this->profileNameFormatter->formatUser($user));
    }

    public function testShowsAUserWithoutAUsernameAsPlainText(): void
    {
        $this->assertSame('Alice', $this->profileNameFormatter->formatUser($this->userRecord(firstName: 'Alice')));
    }

    protected function setUp(): void
    {
        $this->profileNameFormatter = new ProfileNameFormatter(new MarkdownV2());
    }
}
