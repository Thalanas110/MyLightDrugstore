<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation;

use App\Modules\Operations\Application\GetServerTimeAction;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

final class ServerTimeController
{
    public function __construct(private readonly GetServerTimeAction $getServerTime) {}

    public function __invoke(): JsonResponse
    {
        $serverTime = $this->getServerTime->execute()
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');

        return response()->json(['data' => ['now' => $serverTime]]);
    }
}
