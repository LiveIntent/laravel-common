<?php

namespace LiveIntent\LaravelCommon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConvertRequestToSnakeCase
{
    /**
     * Internally we use snake case to match the database fields, but to the external
     * world we'll use camelCase since that's the more common convention for json.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $request->replace(
            $request->collect()->snakeCaseKeys()->snakeCaseValues(['includes', 'sort'])->toArray()
        );

        return $next($request);
    }
}
