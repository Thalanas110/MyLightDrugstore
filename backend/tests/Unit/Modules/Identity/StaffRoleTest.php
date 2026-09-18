<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Identity;

use App\Modules\Identity\Domain\Users\StaffRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class StaffRoleTest extends TestCase
{
    #[DataProvider('roleCapabilities')]
    public function test_role_capabilities_match_the_api_contract(
        StaffRole $role,
        bool $canManageUsers,
        bool $canListBackups,
        bool $canRequestBackup,
    ): void {
        $this->assertSame($canManageUsers, $role->canManageUsers());
        $this->assertSame($canListBackups, $role->canListBackups());
        $this->assertSame($canRequestBackup, $role->canRequestBackup());
    }

    /**
     * @return array<string, array{StaffRole, bool, bool, bool}>
     */
    public static function roleCapabilities(): array
    {
        return [
            'administrator' => [StaffRole::Admin, true, true, true],
            'staff' => [StaffRole::Staff, false, false, true],
        ];
    }
}
