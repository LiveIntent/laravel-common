<?php

namespace LiveIntent\LaravelCommon\Routing\Middleware;

use Closure;
use Illuminate\Http\Request;

class AssumeJson
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
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
