<?php

namespace App\Http\Middleware;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemLog;

class SystemLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        try {
            $response = $next($request);

            $this->storeLog($request, $response, $start, false, null);

            return $response;
        } catch (Throwable $e) {

            $this->storeLog($request, null, $start, true, $e);

            throw $e;
        }
    }
    private function storeLog(Request $request, $response, float $start, bool $isError, ?Throwable $exception)
    {
        $execution = (microtime(true) - $start) * 1000;

        // تنظيف request من الملفات
        if ($request->isMethod('post')) {
            $cleanRequest = [];
        } else {
            $cleanRequest = $request->except(array_keys($request->files->all()));
        }
        // 🚫 لا نحفظ محتوى الـ PDF لأنو مو UTF8
        if (
            $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse ||
            ($response instanceof \Illuminate\Http\Response &&
                str_contains($response->headers->get('content-type'), 'application/pdf'))
        ) {
            $responsePayload = null; // مهم جداً
        } else {
            $responsePayload = $response ? $response->getContent() : null;
        }

        SystemLog::create([
            'user_id'          => Auth::id(),
            'endpoint'         => $request->path(),
            'method'           => $request->method(),
            'status_code'      => $response?->status() ?? 500,
            'request_payload'  => json_encode($cleanRequest, JSON_UNESCAPED_UNICODE),
            'response_payload' => $responsePayload,
            'is_error'         => $isError,
            'error_message'    => $exception?->getMessage(),
            'error_type'       => $exception ? class_basename($exception) : null,
            'execution_time_ms' => $execution,
        ]);
    }
}
