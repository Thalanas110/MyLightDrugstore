<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\Operations\Application\GetServerTimeAction;
use Tests\TestCase;

final class OperationsModuleProviderTest extends TestCase
{
    public function test_service_container_resolves_server_time_in_utc(): void
    {
        $serverTime = app(GetServerTimeAction::class)->execute();

        $this->assertSame('UTC', $serverTime->getTimezone()->getName());
    }
}
