<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class UserAuthMiddleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        // Priority 1: HttpOnly cookie (set by AttachAuthCookies)
        $token = $request->cookie('login_token');
        $email = $request->cookie('login_email');

        // Priority 2: Manual decrypt fallback
        if (!$token && isset($_COOKIE['login_token'])) {
            try {
                $token = Crypt::decryptString($_COOKIE['login_token']);
            } catch (\Exception $e) {
                $token = $_COOKIE['login_token'];
            }
        }
        if (!$email && isset($_COOKIE['login_email'])) {
            try {
                $email = Crypt::decryptString($_COOKIE['login_email']);
            } catch (\Exception $e) {
                $email = $_COOKIE['login_email'];
            }
        }

        // Priority 3: Session fallback
        if (!$token) $token = session('login_token');
        if (!$email) $email = session('user_email');

        // Priority 4: Legacy Bearer header
        if (!$token) {
            $authHeader = $request->header('Authorization');
            if ($authHeader) {
                $token = str_replace('Bearer ', '', $authHeader);
            }
        }
        if (!$email) {
            $email = $request->email ?? $request->query('email');
        }

        if (!$token || !$email) {
            return response()->json([
                'status'  => 0,
                'message' => 'Please login first',
            ], 401);
        }

        $data = DB::table('user_tokens')
            ->where('token', $token)
            ->where('email', $email)
            ->first();

        if (!$data) {
            return response()->json([
                'status'  => 0,
                'message' => 'Invalid session, please login again',
            ], 401);
        }

        if (Carbon::now()->gt(Carbon::parse($data->expires_at))) {
            session()->flush();
            return response()->json([
                'status'  => 0,
                'message' => 'Session expired, please login again',
            ], 401);
        }

        $userRecord = DB::table('user')->where('email', $email)->first();
        $request->merge([
            'email'   => $email,
            'user_id' => $userRecord->user_id ?? session('user_id') ?? $request->user_id,
        ]);

        return $next($request);
    }
}
