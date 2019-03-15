<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class LogRequests
{
    public function handle($request, Closure $next)
    {
        $request->start = microtime(true);

        return $next($request);
    }

    public function terminate($request, $response)
    {
        $request->end = microtime(true);

        $this->log($request,$response);
    }

    protected function log($request,$response)
    {
        $duration = $request->end - $request->start;
        $url = $request->fullUrl();
        $method = $request->getMethod();
        $ip = $request->getClientIp();
        $body = json_encode($request->all(), JSON_PRETTY_PRINT);
        $log = "{$ip}: {$method}@{$url} - {$duration}ms \n".
            "Request : {[$body]} \n".
            "Response : {$response->getContent()} \n";

        Log::debug($log);
    }
}