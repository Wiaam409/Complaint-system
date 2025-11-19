<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data = null, $message = 'Success', $status = 200)
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error($message = 'Error', $status = 400, $data = null)
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }
}
