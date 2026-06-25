<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachAuthCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Attach login cookies AFTER controller returns response
        $token = session('pending_login_token');
        $email = session('pending_login_email');

        if ($token && $email) {
            $response->headers->setCookie(
                cookie('login_token', $token, 480, '/', null, true, true, false, 'none')
            );
            $response->headers->setCookie(
                cookie('login_email', $email, 480, '/', null, true, true, false, 'none')
            );
            session()->forget(['pending_login_token', 'pending_login_email']);
        }

        // Clear cookies on logout
        if (session('logout_cookies')) {
            $response->headers->setCookie(
                cookie('login_token', '', -1, '/', null, true, true, false, 'none')
            );
            $response->headers->setCookie(
                cookie('login_email', '', -1, '/', null, true, true, false, 'none')
            );
            session()->forget('logout_cookies');
        }

        return $response;
    }
}
