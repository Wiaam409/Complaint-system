<?php

use App\Http\Controllers\Authentication\AuthController;
use App\Http\Controllers\Authentication\EmailVerificationController;
use App\Http\Controllers\Authentication\ResetPasswordController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\FcmController;
use App\Mail\VerificationCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/test', function () {
    Mail::to('wiaam409@gmail.com')->send(new VerificationCodeMail(123456));
    return \response()->json(['hello']);
});


Route::controller(AuthController::class)->group(function () {
    Route::post('signup', 'signUp')->name('user.sign_up');
    Route::post('signin', 'signIn')->name('user.sign_in');
    Route::get('signout', 'signOut')->middleware('auth:sanctum');

});

Route::controller(ResetPasswordController::class)->group(function () {
    Route::post('forgotPassword', 'forgotPassword')->name('check.email_forget_password');
    Route::post('checkCode', 'checkCode')->name('check.code');
    Route::post('resetPassword', 'resetPassword')->name('check.password_reset');
});

Route::controller(EmailVerificationController::class)->group(function () {
    Route::post('verifyEmail', 'verifyEmail')->name('check.email_verification');
    Route::post('resendVerificationCode', 'resendVerificationCode')->name('check.verification_code');
});



Route::middleware('auth:sanctum', 'role:citizen')->group(function () {
       Route::post('complaints', [ComplaintController::class, 'store']);
    Route::post('complaints/{id}/update', [ComplaintController::class, 'Update']);
    Route::post('complaints/{id}/submit', [ComplaintController::class, 'submitUpdatedComplaint']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('complaints/{id}', [ComplaintController::class, 'updateStatus']);
    Route::get('comlaintsdepartment', [ComplaintController::class, 'employeeComplaints']);
    Route::post('complaints/{id}/submit-update', [ComplaintController::class, 'requestUpdate']);
});



Route::get('governorates', [ComplaintController::class, 'governorates']);
Route::get('alldepartments', [ComplaintController::class, 'listGroupedDepartments']);
Route::get('departmentsbygovernorates', [ComplaintController::class, 'getDepartmentsByGovernorate']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/fcm-token', [FcmController::class, 'storeToken']);
    Route::post('/send-test-notification', [FcmController::class, 'sendTestNotification']);
});
