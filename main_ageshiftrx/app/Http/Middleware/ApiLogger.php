<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiLogger
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $response = $next($request);
        $executionTime = microtime(true) - $startTime;

        try {

            $logAll = config('api_logger.log_all');
            $allowedUrls = config('api_logger.only_urls');

            // ✅ correct condition
            $shouldLog = $logAll;

            if (!$logAll) {
                foreach ($allowedUrls as $url) {
                    if ($request->is($url)) {
                        $shouldLog = true;
                        break;
                    }
                }
            }

            if (!$shouldLog) {
                return $response;
            }

            $requestBody = $request->except([
                'password',
                'token',
                'authorization',
                'card_number',
                'cvv'
            ]);

            $responseBody = null;
            $contentType = $response->headers->get('content-type');

            if ($contentType && str_contains($contentType, 'application/json')) {
                $responseBody = $response->getContent();
            }

            DB::table('api_logs')->insert([
                'method'          => $request->method(),
                'url'             => $request->fullUrl(),
                'request_headers' => json_encode($request->headers->all()),
                'request_body'    => json_encode($requestBody),
                'status_code'     => $response->getStatusCode(),
                'response_body'   => $responseBody,
                'ip_address'      => $request->ip(),
                'execution_time'  => round($executionTime, 2),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

        } catch (\Exception $e) {
            // silent
        }

        return $response;
    }
}
