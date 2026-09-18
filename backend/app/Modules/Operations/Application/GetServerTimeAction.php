<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Operations\Domain\Clock;
use DateTimeImmutable;

final class GetServerTimeAction
{
    public function __construct(private readonly Clock $clock) {}

    public function execute(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
