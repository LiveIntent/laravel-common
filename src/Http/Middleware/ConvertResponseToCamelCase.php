<?php

namespace LiveIntent\LaravelCommon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConvertResponseToCamelCase
{
    /**
     * Internally we use snake case to match the database fields, but to the external
     * world we'll use camelCase since that's the more common convention for json.
     *
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $content = json_decode($response->getContent(), true);
        $json = collect($content)->camelCaseKeys();

        $response->setContent($json);

        return $response;
    }
}