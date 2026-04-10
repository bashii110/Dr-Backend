<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Public Routes (no auth required)
|--------------------------------------------------------------------------
*/

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register',         [AuthController::class, 'register']);
    Route::post('/verify-otp',       [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp',       [AuthController::class, 'resendOtp']);
    Route::post('/login',            [AuthController::class, 'login']);
    Route::post('/forgot-password',  [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',   [AuthController::class, 'resetPassword']);
});

// Public doctor endpoints
Route::prefix('doctors')->group(function () {
    Route::get('/',            [DoctorController::class, 'index']);
    Route::get('/categories',  [DoctorController::class, 'categories']);
    Route::get('/{id}',        [DoctorController::class, 'show']);
    Route::get('/{id}/slots',  [DoctorController::class, 'availableSlots']);
    Route::get('/{id}/reviews',[ReviewController::class, 'doctorReviews']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum auth required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Profile
    Route::get('/profile',            [ProfileController::class, 'show']);
    Route::post('/profile',           [ProfileController::class, 'update']);
    Route::post('/profile/password',  [ProfileController::class, 'changePassword']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/profile/history',    [ProfileController::class, 'medicalHistory']);

    // Doctor — profile + dashboard + appointments
    Route::post('/doctor/profile',                      [DoctorController::class, 'updateProfile']);
    Route::get('/doctor/dashboard',                     [DoctorController::class, 'dashboard']);
    Route::get('/doctor/appointments',                  [DoctorController::class, 'myAppointments']);
    Route::patch('/doctor/appointments/{id}/status',    [DoctorController::class, 'updateAppointmentStatus']);

    // Patient appointments
    Route::prefix('appointments')->group(function () {
        Route::get('/',                [AppointmentController::class, 'myAppointments']);
        Route::post('/',               [AppointmentController::class, 'store']);
        Route::get('/{id}',            [AppointmentController::class, 'show']);
        Route::post('/{id}/cancel',    [AppointmentController::class, 'cancel']);
        Route::post('/{id}/confirm',   [AppointmentController::class, 'confirm']);
        Route::post('/{id}/complete',  [AppointmentController::class, 'complete']);
        Route::post('/{id}/reschedule',[AppointmentController::class, 'reschedule']);
    });

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Notifications
    Route::get('/notifications', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'status'        => 200,
            'notifications' => $request->user()->notifications()->latest()->take(20)->get(),
            'unread_count'  => $request->user()->unreadNotifications()->count(),
        ]);
    });

    Route::post('/notifications/read-all', function (\Illuminate\Http\Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['status' => 200, 'message' => 'All notifications marked as read.']);
    });
});