<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity;

use App\Modules\Identity\Domain\Users\Username;
use InvalidArgumentException;
use Tests\TestCase;

final class UsernameTest extends TestCase
{
    public function test_it_normalizes_outer_whitespace_and_ascii_case(): void
    {
        $username = new Username('  Pharmacy.Staff_1  ');

        $this->assertSame('pharmacy.staff_1', $username->value);
    }

    public function test_it_allows_only_documented_ascii_username_characters(): void
    {
        $username = new Username('staff-2026');

        $this->assertSame('staff-2026', $username->value);
    }

    public function test_it_rejects_empty_short_long_and_non_ascii_names(): void
    {
        $invalidUsernames = [
            '',
            '  ',
            'ab',
            str_repeat('a', 65),
            'staff name',
            'médic',
        ];

        foreach ($invalidUsernames as $invalidUsername) {
            try {
                new Username($invalidUsername);
                $this->fail('An invalid username was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
