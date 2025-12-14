<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

Route::get('/sd', function () {
    return view('welcome');
});
Route::get('/test', [\App\Http\Controllers\FcmController::class, 'sendTestNotification']);

Route::get('/', function () {
    $serverPort = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 'unknown';
    $serverName = gethostname();

    // Store visit count in Redis
    $visitKey = "server:{$serverPort}:visits";
    $visits = Redis::incr($visitKey);

    return response()->json([
        'message' => 'Complaint System API',
        'server' => $serverName,
        'port' => $serverPort,
        'visits' => $visits,
        'client_ip' => request()->ip(),
        'session_id' => session()->getId(),
        'timestamp' => now()->toDateTimeString()
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'server' => gethostname(),
        'port' => isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 'unknown',
        'timestamp' => now()
    ]);
});

Route::get('complaints', [App\Http\Controllers\ComplaintController::class, 'index']);
