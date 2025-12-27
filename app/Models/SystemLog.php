<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $table = 'system_logs';

    protected $fillable = [
        'user_id',
        'endpoint',
        'method',
        'status_code',
        'response_payload',
        'is_error',
        'error_message',
        'error_type',
        'execution_time_ms',
    ];

    // casts
    protected $casts = [
        'is_error' => 'boolean',
        'execution_time_ms' => 'float',
    ];
}
