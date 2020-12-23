<?php

namespace App\Http\Middleware;

use Closure;

class AttachCountry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $country = $request->header('country', null);
        if ($country) {
            $request->attributes->add(['country' => $country]);
        }
        $response = $next($request);

        return $response;
    }
}
