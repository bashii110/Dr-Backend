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
|
| Flutter ApiService calls these paths (relative to /api):
|   POST /register
|   POST /verify-otp
|   POST /resend-otp
|   POST /login
|   POST /forgot-password
|   GET  /doctors
|   GET  /doctors/categories
|   GET  /doctors/{id}
|   GET  /doctors/{id}/reviews
*/

// Auth — no prefix (Flutter posts to /api/register, /api/login, etc.)
Route::post('/register',        [AuthController::class, 'register']);
Route::post('/verify-otp',      [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp',      [AuthController::class, 'resendOtp']);
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password',  [AuthController::class, 'resetPassword']);

// Public doctor endpoints
Route::prefix('doctors')->group(function () {
    Route::get('/',             [DoctorController::class, 'index']);
    Route::get('/categories',   [DoctorController::class, 'categories']);
    Route::get('/{id}',         [DoctorController::class, 'show']);
    Route::get('/{id}/slots',   [DoctorController::class, 'availableSlots']);
    Route::get('/{id}/reviews', [ReviewController::class, 'doctorReviews']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum auth required)
|--------------------------------------------------------------------------
|
| Flutter ApiService calls these paths:
|   POST /logout
|   GET  /me
|   GET  /profile
|   PUT  /profile
|   POST /profile/change-password
|   PUT  /doctor/profile
|   GET  /doctor/appointments
|   PATCH /doctor/appointments/{id}/status
|   GET  /appointments
|   POST /appointments
|   PATCH /appointments/{id}/cancel
|   POST /reviews
*/

Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── Profile ───────────────────────────────────────────────────────────
    // Flutter calls GET/PUT /profile and POST /profile/change-password
    Route::get('/profile',                  [ProfileController::class, 'show']);
    Route::put('/profile',                  [ProfileController::class, 'update']);
    Route::post('/profile',                 [ProfileController::class, 'update']);   // fallback
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/profile/history',          [ProfileController::class, 'medicalHistory']);

    // ── Doctor profile & dashboard ────────────────────────────────────────
    // Flutter calls PUT /doctor/profile
    Route::put('/doctor/profile',  [DoctorController::class, 'updateProfile']);
    Route::post('/doctor/profile', [DoctorController::class, 'updateProfile']); // fallback
    Route::get('/doctor/dashboard', [DoctorController::class, 'dashboard']);

    // ── Doctor appointments ───────────────────────────────────────────────
    // Flutter calls GET  /doctor/appointments
    //               PATCH /doctor/appointments/{id}/status
    Route::get('/doctor/appointments',
        [AppointmentController::class, 'doctorAppointments']);
    Route::patch('/doctor/appointments/{id}/status',
        [AppointmentController::class, 'updateStatus']);

    // ── Patient / shared appointments ─────────────────────────────────────
    // Flutter calls GET  /appointments          → index()
    //               POST /appointments          → book()
    //               PATCH /appointments/{id}/cancel → cancel()
    Route::get('/appointments',          [AppointmentController::class, 'index']);
    Route::post('/appointments',         [AppointmentController::class, 'book']);
    Route::get('/appointments/{id}',     [AppointmentController::class, 'show']);
    Route::patch('/appointments/{id}/cancel',    [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{id}/cancel',     [AppointmentController::class, 'cancel']); // fallback
    Route::post('/appointments/{id}/confirm',    [AppointmentController::class, 'confirm']);
    Route::post('/appointments/{id}/complete',   [AppointmentController::class, 'complete']);
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);

    // ── Reviews ───────────────────────────────────────────────────────────
    Route::post('/reviews', [ReviewController::class, 'store']);

    // ── Notifications ─────────────────────────────────────────────────────
    Route::get('/notifications', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'status'        => 200,
            'notifications' => $request->user()->notifications()->latest()->take(20)->get(),
            'unread_count'  => $request->user()->unreadNotifications()->count(),
        ]);
    });

    Route::post('/notifications/read-all', function (\Illuminate\Http\Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json([
            'status'  => 200,
            'message' => 'All notifications marked as read.',
        ]);
    });
});