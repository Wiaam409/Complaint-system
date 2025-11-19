<?php

namespace App\Services\Authentication;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Exception;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService
{
    public function verifyEmail($request)
    {
        $request->validate([
            'verification_code' => 'required|string|exists:verification_codes',
        ]);
        // find the code
        $verification = VerificationCode::firstWhere('verification_code', $request->verification_code);

        // check if it does not expired: the time is one hour
        if ($verification->created_at->addHour() < now()) {
            $verification->delete();
            throw new Exception(__('messages.verification_code_expired'));
        }
        $user = User::query()->where('email', $verification['email'])->first();
        $user['email_verified_at'] = now();
        $user->save();
        VerificationCode::firstWhere('email', $verification['email'])->delete();
        return ['message' => __('messages.email_verified')];
    }

    public function resendVerificationCode($request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        $user = User::query()->where('email', $request['email'])->first();
        if (is_null($user)) {
            throw new Exception(__('messages.email_not_found'), 404);
        }
        VerificationCode::query()->where('email', $user['email'])->delete();
        $verification_code = mt_rand(100000, 999999);
        $data = [
            'email' => $user['email'],
            'verification_code' => $verification_code
        ];
        VerificationCode::create($data);
        Mail::to($user['email'])->send(new VerificationCodeMail($verification_code));
        return ['message' => __('messages.verification_code_resent')];
    }
}
