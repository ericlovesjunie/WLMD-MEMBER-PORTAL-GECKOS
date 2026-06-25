<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserAuthMiddleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        $token = $request->header('Authorization');
//        $email = $request->email;  // required now
$email = $request->email ?? $request->query('email');
        // Check both exist
        if (!$token || !$email) {
            return response()->json([
                'status' => 0,
                'message' => 'Token or Email missing'
            ], 401);
        }

        // Remove Bearer
        $token = str_replace('Bearer ', '', $token);

        // 🔥 Check BOTH email + token
        $data = DB::table('user_tokens')
            ->where('token', $token)
            ->where('email', $email)
            ->first();

        if (!$data) {
            return response()->json([
                'status' => 0,
                'message' => 'Email and token do not match'
            ], 401);
        }

        // Check expiry
        if (Carbon::now()->gt(Carbon::parse($data->expires_at))) {
            return response()->json([
                'status' => 0,
                'message' => 'Token expired, please login again'
            ], 401);
        }

        // Attach email
        $request->email = $email;

        return $next($request);
    }
}
