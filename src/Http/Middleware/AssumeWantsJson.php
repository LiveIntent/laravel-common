<?php

namespace LiveIntent\LaravelCommon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AssumeWantsJson
{
    /**
     * Use this in api routes since the user wants a json response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('accept', 'application/json');

        return $next($request);
    }
}
