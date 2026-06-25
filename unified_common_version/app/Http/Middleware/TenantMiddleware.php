<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenants = config('tenants');

        // Read domain from Origin header
        $origin = $request->header('Origin') ?? '';
        $domain = preg_replace('#^https?://#', '', $origin);
        $domain = strtolower(trim(explode('/', $domain)[0]));

        // Fallback to Host header
        if (!$domain || !isset($tenants[$domain])) {
            $domain = strtolower(trim($request->header('Host', '')));
        }

        if (!isset($tenants[$domain])) {
            return response()->json([
                'status'  => 0,
                'message' => 'Unknown tenant: ' . $domain,
            ], 403);
        }

        $database = $tenants[$domain];

        // Switch only the database name — preserve ALL other config
        // including SSL options, host, port, username, password
        Config::set('database.connections.mysql.database', $database);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $request->merge([
            'tenant_domain'   => $domain,
            'tenant_database' => $database,
        ]);

        return $next($request);
    }
}
