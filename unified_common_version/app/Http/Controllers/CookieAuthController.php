<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CookieAuthController
 *
 * Wraps the existing user_login / logout logic to set / clear
 * HttpOnly, Secure, SameSite=None cookies so the React SPA on
 * member.zygos.co never touches the token directly.
 *
 * ADD to routes/api.php:
 *   Route::post('cookie_login',  [CookieAuthController::class, 'login']);
 *   Route::post('cookie_logout', [CookieAuthController::class, 'logout']);
 *   Route::get('cookie_check',   [CookieAuthController::class, 'check']);
 */
class CookieAuthController extends Controller
{
    // ─── How long the cookie (and token) live ────────────────────────
    // Must match create_login_tokens() in ApiController (8 hours)
    private int $cookieMinutes = 480;

    /**
     * POST /api/cookie_login
     *
     * Calls the existing user_login logic, then wraps the response
     * with two HttpOnly cookies: login_token + login_email.
     *
     * The React frontend calls this instead of /api/user_login.
     * On success it gets user info in JSON + cookies set automatically.
     */
    public function login(Request $request)
    {
        // ── Delegate to ApiController's user_login ────────────────────
        /** @var \App\Http\Controllers\ApiController $api */
        $api      = app(ApiController::class);
        $response = $api->user_login($request);

        $payload = $response->getData(true); // decode JSON to array

        if (($payload['status'] ?? 0) !== 1) {
            // Login failed — return error as-is, no cookie
            return $response;
        }

        // ── Build the two HttpOnly cookies ────────────────────────────
        $loginToken = DB::table('user_tokens')
            ->where('email', $payload['email'])
            ->orderBy('created_at', 'desc')
            ->value('token');

        $cookieOptions = $this->cookieOptions();

        $tokenCookie = cookie(
            'login_token',
            $loginToken,
            $this->cookieMinutes,
            $cookieOptions['path'],
            $cookieOptions['domain'],
            $cookieOptions['secure'],
            true,          // HttpOnly :white_check_mark:
            false,
            $cookieOptions['samesite']
        );

        $emailCookie = cookie(
            'login_email',
            $payload['email'],
            $this->cookieMinutes,
            $cookieOptions['path'],
            $cookieOptions['domain'],
            $cookieOptions['secure'],
            true,          // HttpOnly :white_check_mark:
            false,
            $cookieOptions['samesite']
        );

        // ── Remove token from JSON body — cookie carries it ───────────
        unset($payload['login_token']);

        return response()->json($payload)
            ->withCookie($tokenCookie)
            ->withCookie($emailCookie);
    }

    /**
     * POST /api/cookie_logout
     *
     * Deletes the token from DB, flushes session, clears both cookies.
     */
    public function logout(Request $request)
    {
        $email = $request->cookie('login_email') ?? session('user_email');

        if ($email) {
            DB::table('user_tokens')->where('email', $email)->delete();
        }

        session()->flush();

        $opts = $this->cookieOptions();

        $expiredToken = cookie(
            'login_token', '', -1,
            $opts['path'], $opts['domain'], $opts['secure'], true, false, $opts['samesite']
        );
        $expiredEmail = cookie(
            'login_email', '', -1,
            $opts['path'], $opts['domain'], $opts['secure'], true, false, $opts['samesite']
        );

        return response()->json(['status' => 1, 'message' => 'Logged out successfully'])
            ->withCookie($expiredToken)
            ->withCookie($expiredEmail);
    }

    /**
     * GET /api/cookie_check
     *
     * Lets the React app verify whether the user is still authenticated
     * without storing anything in JS.  Returns minimal user info.
     */
    public function check(Request $request)
    {
        $token = $request->cookie('login_token');
        $email = $request->cookie('login_email');

        if (!$token || !$email) {
            return response()->json(['authenticated' => false], 401);
        }

        $data = DB::table('user_tokens')
            ->where('token', $token)
            ->where('email', $email)
            ->first();

        if (!$data || Carbon::now()->gt(Carbon::parse($data->expires_at))) {
            return response()->json(['authenticated' => false], 401);
        }

        $user = DB::table('user')->where('email', $email)->first();

        return response()->json([
            'authenticated' => true,
            'user_id'       => $user->user_id ?? null,
            'first_name'    => $user->first_name ?? '',
            'last_name'     => $user->last_name ?? '',
            'email'         => $email,
        ]);
    }

    // ─── Shared cookie option builder ─────────────────────────────────
    private function cookieOptions(): array
    {
        $isProduction = app()->environment('production');

        return [
            'path'     => '/',
            // null = current domain; fine for same-domain setups.
            // For cross-domain (panel.whitelabelmd.com :left_right_arrow: member.zygos.co)
            // cookies CANNOT share a domain — each origin sets its own.
            'domain'   => null,
            // Must be true when SameSite=None
            'secure'   => $isProduction,
            // 'none' is required for cross-site cookie sending.
            // Use 'lax' if both frontend and backend are on the same eTLD+1.
            'samesite' => 'none',
        ];
    }
}
