<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Users;

enum StaffRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canListBackups(): bool
    {
        return $this === self::Admin;
    }

    public function canRequestBackup(): bool
    {
        return true;
    }
}
