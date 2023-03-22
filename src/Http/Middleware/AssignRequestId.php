<?php

namespace LiveIntent\LaravelCommon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;

/**
 * @deprecated Use LogWithRequestContext instead
 * @see LogWithRequestContext
 */
class AssignRequestId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $requestId = $request->header('x-request-id') ?: str()->uuid();

        Log::withContext([
            'request-id' => $requestId,
        ]);

        return $next($request)->header('Request-Id', $requestId);
    }
}
