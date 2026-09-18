<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Modules\Operations\Domain\Clock;
use DateTimeImmutable;

final class FrozenClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
