<?php
namespace App\Http\Middleware;

use Closure;

class ApiKeyAuth
{
    public function handle($request, Closure $next)
    {
        $clientKey = $request->header('api-key');
        $serverKey = env('AGESHIFT_API_KEY');
        // dd($clientKey, $serverKey);

        if (!$clientKey || $clientKey !== $serverKey) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid API Key'
            ], 401);
        }

        return $next($request);
    }
}
