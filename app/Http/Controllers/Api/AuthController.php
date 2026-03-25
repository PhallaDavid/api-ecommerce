<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\EmailOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Check if email already exists
        if (!empty($validated['email']) && User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'This email is already registered.'
            ], 409);
        }

        // Check if phone already exists
        if (!empty($validated['phone']) && User::where('phone', $validated['phone'])->exists()) {
            return response()->json([
                'message' => 'This phone number is already registered.'
            ], 409);
        }

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        // Return success response
        return response()->json([
            'message' => 'User registered successfully!',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'password' => 'required|string',
        ]);

        $user = null;
        if (!empty($validated['email'])) {
            $user = User::where('email', $validated['email'])->first();
        } elseif (!empty($validated['phone'])) {
            $user = User::where('phone', $validated['phone'])->first();
        }

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    public function forgotPasswordOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Always respond success to avoid email enumeration
        if (!$user) {
            return response()->json([
                'message' => 'If your email exists, an OTP has been sent.'
            ], 200);
        }

        $otp = (string) random_int(100000, 999999);

        EmailOtp::where('email', $validated['email'])
            ->where('purpose', 'password_reset')
            ->delete();

        EmailOtp::create([
            'email' => $validated['email'],
            'purpose' => 'password_reset',
            'code_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::raw(
            "Your OTP code is {$otp}. It expires in 10 minutes.",
            function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Your password reset code');
            }
        );

        return response()->json([
            'message' => 'If your email exists, an OTP has been sent.'
        ], 200);
    }

    public function resetPasswordWithOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $otpRecord = EmailOtp::where('email', $validated['email'])
            ->where('purpose', 'password_reset')
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->delete();
            return response()->json([
                'message' => 'OTP expired.'
            ], 400);
        }

        if (!Hash::check($validated['otp'], $otpRecord->code_hash)) {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $otpRecord->delete();

        return response()->json([
            'message' => 'Password reset successfully.'
        ], 200);
    }

    public function sendEmailVerificationOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified.'
            ], 200);
        }

        $otp = (string) random_int(100000, 999999);

        EmailOtp::updateOrCreate(
            [
                'email' => $validated['email'],
                'purpose' => 'email_verify',
            ],
            [
                'code_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
            ]
        );

        Mail::raw(
            "Your email verification OTP is {$otp}. It expires in 10 minutes.",
            function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Verify your email');
            }
        );

        return response()->json([
            'message' => 'OTP sent to your email.'
        ], 200);
    }

    public function verifyEmailWithOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email already verified.'
            ], 200);
        }

        $otpRecord = EmailOtp::where('email', $validated['email'])
            ->where('purpose', 'email_verify')
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        if ($otpRecord->expires_at->isPast()) {
            $otpRecord->delete();
            return response()->json([
                'message' => 'OTP expired.'
            ], 400);
        }

        if (!Hash::check($validated['otp'], $otpRecord->code_hash)) {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $otpRecord->delete();

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $user->fresh(),
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!$user || !Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.'
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Invalidate existing tokens after password change
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Password changed successfully. Please log in again.'
        ], 200);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'avatar_url' => $request->user()->avatar
                ? Storage::disk('public')->url($request->user()->avatar)
                : null,
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'avatar' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data = [];
        if (array_key_exists('name', $validated)) {
            $data['name'] = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $data['email'] = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $data['phone'] = $validated['phone'];
        }
        if ($request->hasFile('avatar')) {
            if (!empty($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user->fill($data);
        if (array_key_exists('phone', $validated) && $validated['phone'] !== $user->getOriginal('phone')) {
            $user->phone_verified_at = null;
        }
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
            // 'avatar_url' => $user->avatar ? Storage::disk('public')->url($user->avatar) : null,
        ], 200);
    }

    public function sendPhoneVerificationOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        $rawPhone = $validated['phone'];
        $user = User::where('phone', $rawPhone)
            ->orWhere('phone', $this->normalizePhoneForTwilio($rawPhone))
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->phone_verified_at) {
            return response()->json([
                'message' => 'Phone already verified.'
            ], 200);
        }

        $twilioPhone = $this->normalizePhoneForTwilio($rawPhone);
        $response = $this->twilioVerifyRequest('Verifications', [
            'To' => $twilioPhone,
            'Channel' => 'sms',
        ]);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        return response()->json([
            'message' => 'OTP sent to your phone.'
        ], 200);
    }

    public function verifyPhoneWithOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        $rawPhone = $validated['phone'];
        $user = User::where('phone', $rawPhone)
            ->orWhere('phone', $this->normalizePhoneForTwilio($rawPhone))
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->phone_verified_at) {
            return response()->json([
                'message' => 'Phone already verified.'
            ], 200);
        }

        $twilioPhone = $this->normalizePhoneForTwilio($rawPhone);
        $response = $this->twilioVerifyRequest('VerificationCheck', [
            'To' => $twilioPhone,
            'Code' => $validated['otp'],
        ]);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        if (($response['status'] ?? null) !== 'approved') {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        $user->forceFill([
            'phone_verified_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Phone verified successfully.',
            'user' => $user->fresh(),
        ], 200);
    }

    public function forgotPasswordPhoneOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        $rawPhone = $validated['phone'];
        $user = User::where('phone', $rawPhone)
            ->orWhere('phone', $this->normalizePhoneForTwilio($rawPhone))
            ->first();

        // Always respond success to avoid phone enumeration
        if (!$user) {
            return response()->json([
                'message' => 'If your phone exists, an OTP has been sent.'
            ], 200);
        }

        $twilioPhone = $this->normalizePhoneForTwilio($rawPhone);
        $response = $this->twilioVerifyRequest('Verifications', [
            'To' => $twilioPhone,
            'Channel' => 'sms',
        ]);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        return response()->json([
            'message' => 'If your phone exists, an OTP has been sent.'
        ], 200);
    }

    public function resetPasswordWithPhoneOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $rawPhone = $validated['phone'];
        $user = User::where('phone', $rawPhone)
            ->orWhere('phone', $this->normalizePhoneForTwilio($rawPhone))
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        $twilioPhone = $this->normalizePhoneForTwilio($rawPhone);
        $response = $this->twilioVerifyRequest('VerificationCheck', [
            'To' => $twilioPhone,
            'Code' => $validated['otp'],
        ]);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        if (($response['status'] ?? null) !== 'approved') {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        return response()->json([
            'message' => 'Password reset successfully.'
        ], 200);
    }

    private function normalizePhoneForTwilio(string $phone): string
    {
        $phone = trim($phone);
        if (Str::startsWith($phone, '+')) {
            return $phone;
        }

        $countryCode = config('services.twilio.default_country_code', '+855');
        $phone = ltrim($phone, '0');

        return $countryCode . $phone;
    }

    private function twilioVerifyRequest(string $endpoint, array $payload)
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $verifySid = config('services.twilio.verify_sid');

        if (!$accountSid || !$authToken || !$verifySid) {
            return response()->json([
                'message' => 'Twilio is not configured. Please set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_VERIFY_SID.'
            ], 500);
        }

        $url = "https://verify.twilio.com/v2/Services/{$verifySid}/{$endpoint}";

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post($url, $payload);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Twilio request failed.',
                'details' => $response->json(),
            ], 400);
        }

        return $response->json();
    }
}
