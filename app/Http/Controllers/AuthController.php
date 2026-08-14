<?php

namespace App\Http\Controllers;

use App\Models\OtpCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * REGISTER
     *
     * 1. Validate user data
     * 2. Create user with unverified email
     * 3. Generate verification OTP
     * 4. Send OTP email
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'type'     => 'required|in:patient,doctor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'              => $request->name,
            'email'             => strtolower(trim($request->email)),
            'password'          => $request->password,
            'type'              => $request->type,
            'email_verified_at' => null,
        ]);

        // Generate and send verification OTP
        $this->sendOtp($user, 'email_verification');

        return response()->json([
            'status'  => 201,
            'message' => 'Registration successful. Verification OTP sent to your email.',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'type'  => $user->type,
            ],
        ], 201);
    }

    /**
     * VERIFY EMAIL OTP
     *
     * OTP is single-use.
     * After successful verification:
     * email_verified_at = current time
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 400,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        // Already verified
        if ($user->email_verified_at !== null) {
            return response()->json([
                'status'  => 400,
                'message' => 'Email is already verified.',
            ], 400);
        }

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('code', $request->otp)
            ->where('type', 'email_verification')
            ->where('expires_at', '>=', Carbon::now())
            ->where('used', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status'  => 400,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        // Make OTP single-use
        $otpRecord->update([
            'used' => true,
        ]);

        // Mark email as verified
        $user->update([
            'email_verified_at' => now(),
        ]);

        // Create role-specific profile
        if ($user->type === 'doctor') {
            \App\Models\Doctor::firstOrCreate(
                ['doc_id' => $user->id],
                [
                    'category'   => null,
                    'experience' => 0,
                    'patients'   => 0,
                    'bio_data'   => null,
                    'status'     => 'pending',
                ]
            );
        } else {
            \App\Models\UserDetails::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'bio_data' => null,
                    'status'   => 'active',
                ]
            );
        }

        // Create authentication token
        $token = $user
            ->createToken('auth_token')
            ->plainTextToken;

        return response()->json([
            'status'  => 200,
            'message' => 'Email verified successfully!',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * RESEND EMAIL VERIFICATION OTP
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 404,
                'message' => 'Account not found.',
            ], 404);
        }

        // Already verified
        if ($user->email_verified_at !== null) {
            return response()->json([
                'status'  => 400,
                'message' => 'Email already verified.',
            ], 400);
        }

        // Maximum 3 OTP requests per 10 minutes
        $recentCount = OtpCode::where('user_id', $user->id)
            ->where('type', 'email_verification')
            ->where(
                'created_at',
                '>=',
                now()->subMinutes(10)
            )
            ->count();

        if ($recentCount >= 3) {
            return response()->json([
                'status'  => 429,
                'message' => 'Too many OTP requests. Please wait 10 minutes.',
            ], 429);
        }

        $this->sendOtp(
            $user,
            'email_verification'
        );

        return response()->json([
            'status'  => 200,
            'message' => 'OTP resent to your email.',
        ]);
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
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $user = User::where(
        'email',
        strtolower(trim($request->email))
    )->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'status' => 401,
            'message' => 'Invalid email or password.',
        ], 401);
    }

    // Check Laravel's email_verified_at
    if (is_null($user->email_verified_at)) {
        return response()->json([
            'status' => 403,
            'message' => 'Please verify your email before logging in.',
            'requires_verification' => true,
            'email' => $user->email,
        ], 403);
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
    ], 200);
}

    /**
     * LOGOUT
     *
     * Deletes only the current Sanctum token.
     */
    public function logout(Request $request)
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'status'  => 200,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * GET CURRENT USER
     */
    public function me(Request $request)
    {
        $user = $request->user()
            ->load([
                'doctor',
                'userDetails',
            ]);

        return response()->json([
            'status' => 200,
            'user'   => $this->formatUser($user),
        ]);
    }

    /**
     * FORGOT PASSWORD
     *
     * Sends password reset OTP.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));

        $user = User::where('email', $email)->first();

        /*
         * Security:
         * Do not reveal whether an email exists.
         */
        if (!$user) {
            return response()->json([
                'status'  => 200,
                'message' => 'If an account exists with this email, a reset OTP has been sent.',
            ]);
        }

        /*
         * Only verified email accounts can reset password.
         */
        if ($user->email_verified_at === null) {
            return response()->json([
                'status'  => 400,
                'message' => 'Please verify your email before resetting your password.',
            ], 400);
        }

        // Maximum 3 reset OTP requests per 10 minutes
        $recentCount = OtpCode::where('user_id', $user->id)
            ->where('type', 'password_reset')
            ->where(
                'created_at',
                '>=',
                Carbon::now()->subMinutes(10)
            )
            ->count();

        if ($recentCount >= 3) {
            return response()->json([
                'status'  => 429,
                'message' => 'Too many reset requests. Please wait 10 minutes.',
            ], 429);
        }

        $this->sendOtp(
            $user,
            'password_reset'
        );

        return response()->json([
            'status'  => 200,
            'message' => 'Password reset OTP sent to your email.',
        ]);
    }

    /**
     * VERIFY PASSWORD RESET OTP
     *
     * OTP verification does NOT change the password.
     *
     * If valid:
     * - OTP becomes used
     * - Short-lived reset token is generated
     * - Reset token is returned to Flutter
     */
    public function verifyResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $otp   = trim($request->otp);

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 400,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('code', $otp)
            ->where('type', 'password_reset')
            ->where('expires_at', '>=', now())
            ->where('used', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'status'  => 400,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        // OTP can never be used again
        $otpRecord->update([
            'used' => true,
        ]);

        /*
         * Generate cryptographically secure reset token.
         */
        $resetToken = bin2hex(
            random_bytes(32)
        );

        $resetTokenHash = hash(
            'sha256',
            $resetToken
        );

        /*
         * Store only the hash.
         * Token expires after 10 minutes.
         */
        cache()->put(
            'password_reset:' . $resetTokenHash,
            [
                'user_id' => $user->id,
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'status'      => 200,
            'message'     => 'OTP verified successfully.',
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * RESET PASSWORD
     *
     * Requires the reset token received after
     * successful password-reset OTP verification.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reset_token'           => 'required|string',
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tokenHash = hash(
            'sha256',
            $request->reset_token
        );

        $resetData = cache()->get(
            'password_reset:' . $tokenHash
        );

        if (!$resetData || empty($resetData['user_id'])) {
            return response()->json([
                'status'  => 400,
                'message' => 'Invalid or expired reset authorization.',
            ], 400);
        }

        $user = User::find(
            $resetData['user_id']
        );

        if (!$user) {
            cache()->forget(
                'password_reset:' . $tokenHash
            );

            return response()->json([
                'status'  => 400,
                'message' => 'Invalid reset authorization.',
            ], 400);
        }

        /*
         * Change password.
         */
        $user->update([
            'password' => Hash::make(
                $request->password
            ),
        ]);

        /*
         * Reset token is single-use.
         */
        cache()->forget(
            'password_reset:' . $tokenHash
        );

        /*
         * Revoke existing sessions.
         */
        $user->tokens()->delete();

        return response()->json([
            'status'  => 200,
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * SEND OTP
     *
     * Handles:
     * - email_verification
     * - password_reset
     */
    private function sendOtp(
        User $user,
        string $type = 'email_verification'
    ) {
        /*
         * Invalidate previous unused OTPs
         * of the same type.
         */
        OtpCode::where('user_id', $user->id)
            ->where('type', $type)
            ->where('used', false)
            ->update([
                'used' => true,
            ]);

        /*
         * Generate 6-digit OTP.
         */
        $code = str_pad(
            random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT
        );

        /*
         * Save OTP.
         */
        OtpCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'type'       => $type,
            'expires_at' => now()->addMinutes(10),
            'used'       => false,
        ]);

        /*
         * Send email.
         */
        try {
            Mail::to($user->email)->send(
                new \App\Mail\OtpMail(
                    $user,
                    $code,
                    $type
                )
            );

            Log::info('OTP email sent', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'type'    => $type,
            ]);
        } catch (\Exception $e) {
            Log::error('OTP email failed', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * FORMAT USER RESPONSE
     */
    private function formatUser(User $user): array
    {
        $base = [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'phone'              => $user->phone ?? null,
            'type'               => $user->type,
            'email_verified_at'  => $user->email_verified_at,
            'profile_photo_path' => $user->profile_photo_path ?? null,
            'created_at'         => $user->created_at,
        ];

        /*
         * Doctor profile.
         */
        if (
            $user->type === 'doctor' &&
            $user->relationLoaded('doctor') &&
            $user->doctor
        ) {
            $base['profile'] = $user->doctor->toArray();
        }

        /*
         * Patient profile.
         */
        if (
            $user->type === 'patient' &&
            $user->relationLoaded('userDetails') &&
            $user->userDetails
        ) {
            $base['patient_details'] =
                $user->userDetails->toArray();
        }

        return $base;
    }
}