<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations;

use App\Modules\Operations\Application\GetServerTimeAction;
use DateTimeImmutable;
use Tests\Fixtures\FrozenClock;
use Tests\TestCase;

final class GetServerTimeActionTest extends TestCase
{
    public function test_execute_returns_the_current_time_from_the_clock_port(): void
    {
        $expectedTime = new DateTimeImmutable('2025-04-12T08:15:30+00:00');
        $action = new GetServerTimeAction(new FrozenClock($expectedTime));

        $this->assertSame($expectedTime->format(DATE_ATOM), $action->execute()->format(DATE_ATOM));
    }
}
