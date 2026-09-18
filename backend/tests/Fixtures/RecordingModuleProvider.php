<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use Illuminate\Contracts\Container\Container;

final class RecordingModuleProvider implements ModuleProvider
{
    public int $registrationCount = 0;

    public function __construct(private readonly ModuleName $module) {}

    public function module(): ModuleName
    {
        return $this->module;
    }

    public function register(Container $container): void
    {
        $this->registrationCount++;
    }
}
