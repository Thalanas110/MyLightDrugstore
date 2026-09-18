<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachRequestId
{
    public const HEADER = 'X-Request-ID';

    public const ATTRIBUTE = 'request_id';

    private const SAFE_CLIENT_ID_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->headers->get(self::HEADER);
        $requestId = is_string($candidate)
            && preg_match(self::SAFE_CLIENT_ID_PATTERN, $candidate) === 1
                ? $candidate
                : 'req_'.Str::ulid();

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        Context::add('request_id', $requestId);

        try {
            $response = $next($request);
        } finally {
            Context::forget('request_id');
        }

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
