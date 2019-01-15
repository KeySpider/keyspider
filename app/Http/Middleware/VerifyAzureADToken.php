<?php

namespace App\Http\Middleware;

use App\Exceptions\SCIMException;
use Closure;
use Illuminate\Support\Facades\Log;

class VerifyAzureADToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $authorization = $request->header('authorization');

        if($authorization !== "Bearer ".config('app.azure_token')) {
            dd('ok');
        }

        return $next($request);
    }
}
