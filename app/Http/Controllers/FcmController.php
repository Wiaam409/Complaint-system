<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class FcmController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    // Store device token
    public function storeToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string'
        ]);

        $user = Auth::user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['message' => 'Token stored successfully']);
    }

    // Send test notification
    public function sendTestNotification(Request $request)
    {
        $user = User::query()->find(1);

        if (!$user->fcm_token) {
            return response()->json(['error' => 'No FCM token found'], 400);
        }

        $sent = $this->firebaseService->sendToToken(
            $user->fcm_token,
            'Test Notification',
            'This is a test notification from Laravel',
            ['type' => 'test', 'click_action' => 'FLUTTER_NOTIFICATION_CLICK']
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Notification sent' : 'Failed to send notification'
        ]);
    }
}
