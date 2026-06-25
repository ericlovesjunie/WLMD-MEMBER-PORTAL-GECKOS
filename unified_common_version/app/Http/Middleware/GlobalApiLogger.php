<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

class GlobalApiLog extends Model
{
    protected $table = 'all_api_logs';

    protected $fillable = [
        'method', 'url', 'ip', 'user_agent',
        'request_body', 'response_body',
        'status_code', 'duration_ms', 'error_message',
    ];

    protected $casts = [
        'request_body'  => 'array',
        'response_body' => 'array',
    ];
}

class GlobalApiLogger
{
    protected array $sensitiveFields = [
        'card_no', 'cvv_no', 'password',
    ];

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {

            GlobalApiLog::create([
                'method'        => $request->method(),
                'url'           => $request->fullUrl(),
                'ip'            => $request->getClientIp(),
                'user_agent'    => $request->userAgent() ?? 'N/A',
                'request_body'  => $this->maskSensitive($request->all()),
                'response_body' => [
                    'error' => $e->getMessage(),
                ],
                'status_code'   => 500,
                'duration_ms'   => round((microtime(true) - $startTime) * 1000, 2),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $content = $response->getContent();
        $responseBody = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $responseBody = ['raw' => substr($content, 0, 1000)];
        }

        GlobalApiLog::create([
            'method'        => $request->method(),
            'url'           => $request->fullUrl(),
            'ip'            => $request->getClientIp(),
            'user_agent'    => $request->userAgent() ?? 'N/A',
            'request_body'  => $this->maskSensitive($request->all()),
            'response_body' => $this->maskSensitive($responseBody ?? []),
            'status_code'   => $response->getStatusCode(),
            'duration_ms'   => round((microtime(true) - $startTime) * 1000, 2),
            'error_message' => $response->getStatusCode() >= 400 ? 'Error' : null,
        ]);

        return $response;
    }

    private function maskSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->sensitiveFields)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitive($value);
            }
        }
        return $data;
    }
}