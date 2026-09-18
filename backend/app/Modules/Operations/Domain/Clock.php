<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
