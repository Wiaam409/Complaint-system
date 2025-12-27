<?php

namespace App\Http\Middleware;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemLog;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SystemLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        try {
            $response = $next($request);

            $this->storeLog($request, $response, $start, null);

            return $response;
        } catch (Throwable $e) {

            $this->storeLog($request, null, $start, $e);

            throw $e;
        }
    }

    private function storeLog(
        Request $request,
        $response,
        float $start,
        ?Throwable $exception
    ): void {
        $execution = (microtime(true) - $start) * 1000;

        $statusCode = $response?->status() ?? 500;

        if (
            $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse ||
            ($response instanceof \Illuminate\Http\Response &&
                str_contains((string) $response->headers->get('content-type'), 'application/pdf'))
        ) {
            $responsePayload = null;
        } else {
            $responsePayload = $response?->getContent();
        }

        $isError = $exception !== null || $statusCode >= 400;

        $errorMessage = null;
        $errorType = null;

        if ($exception) {
            $errorMessage = $exception->getMessage();
            $errorType = class_basename($exception);
        } elseif ($statusCode >= 400 && $responsePayload) {
            $decoded = json_decode($responsePayload, true);
            $errorMessage = $decoded['message'] ?? 'HTTP Error';
            $errorType = 'HttpError';
        }

        SystemLog::create([
            'user_id'           => Auth::id(),
            'endpoint'          => $request->path(),
            'method'            => $request->method(),
            'status_code'       => $statusCode,
            'response_payload'  => $statusCode < 400 ? $responsePayload : null,
            'is_error'          => $isError,
            'error_message'     => $errorMessage,
            'error_type'        => $errorType,
            'execution_time_ms' => $execution,
        ]);
    }
}
