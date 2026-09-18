<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity;

use App\Modules\Identity\Domain\Users\FullName;
use InvalidArgumentException;
use Tests\TestCase;

final class FullNameTest extends TestCase
{
    public function test_it_trims_and_collapses_whitespace_without_changing_name_case(): void
    {
        $fullName = new FullName("  Ada   María\tDimate  ");

        $this->assertSame('Ada María Dimate', $fullName->value);
    }

    public function test_it_preserves_valid_unicode_names(): void
    {
        $fullName = new FullName('李 小龍');

        $this->assertSame('李 小龍', $fullName->value);
    }

    public function test_it_rejects_empty_oversized_and_control_character_names(): void
    {
        foreach ([' ', str_repeat('A', 161), "Ada\0Dimate"] as $invalidFullName) {
            try {
                new FullName($invalidFullName);
                $this->fail('An invalid full name was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
