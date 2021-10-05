<?php

namespace LiveIntent\LaravelCommon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdjustResponseCode
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
        $response = $next($request);

        // Magically return 204 to indicate there is nothing for the user to parse.
        // See also: https://github.com/laravel/framework/blob/8.x/src/Illuminate/Routing/Router.php#L773
        if (!json_decode($response->getContent()) && $response->getStatusCode() < 300) {
            $response->setStatusCode(204);
        }

        return $response;
    }
}
