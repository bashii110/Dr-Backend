<?php

namespace App\Http\Controllers;

use App\Models\OtpCode;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{
    /**
     * REGISTER — Step 1: Save user, send OTP
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'     => 'required|string|max:100',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|min:6',
                'type'     => 'required|in:patient,doctor',
                'phone'    => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $userData = [
                'name'              => $request->name,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
                'type'              => $request->type,
                'is_email_verified' => false,
            ];
            if ($request->filled('phone')) {
                $userData['phone'] = $request->phone;
            }

            $user = User::create($userData);

            // Generate + send OTP
            $this->sendOtp($user);

            return response()->json([
                'status'  => 200,   // Flutter checks for 200
                'message' => 'Registered! Please verify your email with the OTP sent.',
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

        } catch (Exception $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * VERIFY OTP — accepts either email or user_id
     */
    public function verifyOtp(Request $request)
    {
        // Accept email (new Flutter code) OR user_id (legacy)
        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        // Resolve user
        $user = null;
        if ($request->filled('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->filled('user_id')) {
            $user = User::find($request->user_id);
        }

        if (!$user) {
            return response()->json(['status' => 422, 'message' => 'Email or user_id is required.'], 422);
        }

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('code', $request->otp)
            ->where('expires_at', '>=', Carbon::now())
            ->where('used', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json(['status' => 400, 'message' => 'Invalid or expired OTP.'], 400);
        }

        $otpRecord->update(['used' => true]);
        $user->update(['is_email_verified' => true, 'email_verified_at' => now()]);

        // Create role-specific profile row
        if ($user->type === 'doctor') {
            \App\Models\Doctor::firstOrCreate(['doc_id' => $user->id], [
                'category'   => null,
                'experience' => 0,
                'patients'   => 0,
                'bio_data'   => null,
                'status'     => 'pending',
            ]);
        } else {
            \App\Models\UserDetails::firstOrCreate(['user_id' => $user->id], [
                'bio_data' => null,
                'status'   => 'active',
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'  => 200,
            'message' => 'Email verified successfully!',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * RESEND OTP — accepts email or user_id
     */
    public function resendOtp(Request $request)
    {
        $user = null;
        if ($request->filled('email')) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->filled('user_id')) {
            $user = User::find($request->user_id);
        }

        if (!$user) {
            return response()->json(['status' => 422, 'message' => 'Email or user_id is required.'], 422);
        }

        if ($user->is_email_verified) {
            return response()->json(['status' => 400, 'message' => 'Email already verified.'], 400);
        }

        // Throttle: max 3 OTPs per 10 minutes
        $recentCount = OtpCode::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->count();

        if ($recentCount >= 3) {
            return response()->json(['status' => 429, 'message' => 'Too many OTP requests. Please wait 10 minutes.'], 429);
        }

        $this->sendOtp($user);

        return response()->json(['status' => 200, 'message' => 'OTP resent to your email.']);
    }

    /**
     * LOGIN
     */
    public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 422,
            'errors' => $validator->errors()
        ], 422);
    }

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'status' => 401,
            'message' => 'Invalid email or password.'
        ], 401);
    }

    // Remove old tokens
    $user->tokens()->delete();

    // Create new token
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'status' => 200,
        'message' => 'Login successful!',
        'token' => $token,
        'user' => $this->formatUser($user),
    ]);
}

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->Token()->delete();
        return response()->json(['status' => 200, 'message' => 'Logged out successfully.']);
    }

    /**
     * GET ME (authenticated user)
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['doctor', 'userDetails']);
        return response()->json(['status' => 200, 'user' => $this->formatUser($user)]);
    }

    /**
     * FORGOT PASSWORD — send OTP
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email|exists:users,email']);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();
        $this->sendOtp($user, 'password_reset');

        return response()->json(['status' => 200, 'message' => 'Password reset OTP sent.', 'user_id' => $user->id]);
    }

    /**
     * RESET PASSWORD
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'      => 'required|exists:users,id',
            'otp'          => 'required|digits:6',
            'new_password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $otpRecord = OtpCode::where('user_id', $request->user_id)
            ->where('code', $request->otp)
            ->where('type', 'password_reset')
            ->where('expires_at', '>=', Carbon::now())
            ->where('used', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json(['status' => 400, 'message' => 'Invalid or expired OTP.'], 400);
        }

        $otpRecord->update(['used' => true]);
        User::find($request->user_id)->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['status' => 200, 'message' => 'Password reset successfully.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function sendOtp(User $user, string $type = 'email_verify')
    {
        OtpCode::where('user_id', $user->id)->where('type', $type)->where('used', false)->update(['used' => true]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'type'       => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
            'used'       => false,
        ]);

        try {
            Mail::to($user->email)->send(new \App\Mail\OtpMail($user, $code, $type));
        } catch (\Exception $e) {
            Log::warning('OTP email failed: ' . $e->getMessage() . ' | OTP: ' . $code);
        }
    }

    private function formatUser(User $user): array
    {
        $base = [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'phone'              => $user->phone ?? null,
            'type'               => $user->type,
            'is_email_verified'  => $user->is_email_verified ?? false,
            'profile_photo_path' => $user->profile_photo_path ?? null,
            'created_at'         => $user->created_at,
        ];

        if ($user->type === 'doctor' && $user->relationLoaded('doctor') && $user->doctor) {
            $base['profile'] = $user->doctor->toArray();
        }

        if ($user->type === 'patient' && $user->relationLoaded('userDetails') && $user->userDetails) {
            $base['patient_details'] = $user->userDetails;
        }

        return $base;
    }
}