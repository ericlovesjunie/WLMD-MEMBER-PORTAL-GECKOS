<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Token retrieve karo
        $token = $request->bearerToken();

        if (empty($token)) {
            return response()->json([
                'status' => 0,
                'message' => "Authorization token is required.",
            ], 401);
        }

        // Token ko hash karo
        $hashedToken = hash('sha256', $token);

        // Database me token check karo
        $accessToken = DB::table('api_tokens')
            ->where('token', $hashedToken)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$accessToken) {
            return response()->json([
                'status' => 0,
                'message' => 'Unauthorized or token expired.',
            ], 401);
        }

        return $next($request);
    }
}
