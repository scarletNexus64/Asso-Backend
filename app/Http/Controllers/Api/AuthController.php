<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PhoneOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Send OTP to phone number (login or register)
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:8|max:20',
            'country_code' => 'nullable|string|max:5',
            'otp_method' => 'nullable|string|in:whatsapp,sms,auto',
        ]);

        $phone = $request->phone;
        $countryCode = $request->country_code ?? '+237';
        $fullPhone = $countryCode . $phone;
        $otpMethod = $request->otp_method ?? 'auto'; // Default to auto if not specified

        Log::info('[AUTH] ========== SEND OTP ==========', [
            'phone' => $fullPhone,
            'otp_method' => $otpMethod,
        ]);

        // Find or create user
        $user = User::where('phone', $fullPhone)->first();

        if (!$user) {
            Log::info('[AUTH] Creating new user', ['phone' => $fullPhone]);
            $user = User::create([
                'phone' => $fullPhone,
                'first_name' => 'Utilisateur',
                'last_name' => substr($phone, -4),
                'email' => 'user_' . time() . '_' . rand(100, 999) . '@asso.app',
                'password' => Hash::make(str()->random(16)),
                'role' => 'client',
                'country' => 'Cameroun',
            ]);
            Log::info('[AUTH] New user created', ['user_id' => $user->id, 'phone' => $fullPhone]);
        } else {
            Log::info('[AUTH] Existing user found', ['user_id' => $user->id, 'phone' => $fullPhone]);
        }

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Log::info('[AUTH] OTP code generated', ['phone' => $fullPhone, 'code' => $otpCode]);

        // Store OTP in user model
        $user->update([
            'otp_code' => $otpCode,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
        ]);
        Log::info('[AUTH] OTP stored in user model', ['user_id' => $user->id]);

        // Store OTP in phone_otps table
        PhoneOtp::create([
            'phone' => $fullPhone,
            'code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);
        Log::info('[AUTH] OTP stored in phone_otps table', ['phone' => $fullPhone]);

        // Send OTP using OtpService with preferred channel
        $otpService = new \App\Services\OtpService();

        // Normalize otp_method: 'auto' becomes null for OtpService
        $preferredChannel = ($otpMethod === 'auto') ? null : $otpMethod;

        $result = $otpService->sendOtp($fullPhone, $otpCode, $preferredChannel);

        Log::info('[AUTH] OTP send result', [
            'phone' => $fullPhone,
            'success' => $result['success'],
            'channel' => $result['channel'],
            'result' => $result
        ]);

        $response = [
            'success' => $result['success'],
            'message' => $result['message'],
            'channel' => $result['channel'],
            'is_new_user' => !$user->is_profile_complete,
        ];

        // In development, return the code
        if (app()->environment('local')) {
            $response['otp_code'] = $otpCode;
        }

        Log::info('[AUTH] Send OTP response', [
            'phone' => $fullPhone,
            'channel' => $result['channel'],
            'success' => $result['success'],
            'is_new_user' => !$user->is_profile_complete,
        ]);

        return response()->json($response);
    }

    /**
     * Verify OTP and return token
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp_code' => 'required|string|size:6',
        ]);

        Log::info('[AUTH] ========== VERIFY OTP ==========', [
            'phone' => $request->phone,
            'otp_code' => $request->otp_code,
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            Log::warning('[AUTH] User not found for OTP verification', ['phone' => $request->phone]);
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé',
            ], 404);
        }

        Log::info('[AUTH] User found, checking OTP', [
            'user_id' => $user->id,
            'phone' => $request->phone
        ]);

        // Check OTP from phone_otps table first
        $phoneOtp = PhoneOtp::where('phone', $request->phone)
            ->where('code', $request->otp_code)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $otpValid = false;

        if ($phoneOtp) {
            Log::info('[AUTH] OTP found in phone_otps table and valid', [
                'phone' => $request->phone,
                'phone_otp_id' => $phoneOtp->id
            ]);
            $otpValid = true;
            $phoneOtp->update(['verified' => true]);
        } elseif ($user->otp_code === $request->otp_code) {
            Log::info('[AUTH] OTP found in user model, checking expiration', [
                'user_id' => $user->id,
                'otp_expires_at' => $user->otp_expires_at
            ]);
            // Fallback: check user model
            if ($user->otp_expires_at && !Carbon::parse($user->otp_expires_at)->isPast()) {
                Log::info('[AUTH] OTP from user model is valid', ['user_id' => $user->id]);
                $otpValid = true;
            } else {
                Log::warning('[AUTH] OTP from user model expired', [
                    'user_id' => $user->id,
                    'otp_expires_at' => $user->otp_expires_at
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Code OTP expiré. Veuillez en demander un nouveau.',
                ], 422);
            }
        } else {
            Log::warning('[AUTH] OTP not found in phone_otps or user model', [
                'phone' => $request->phone,
                'provided_code' => $request->otp_code
            ]);
        }

        if (!$otpValid) {
            Log::error('[AUTH] OTP verification failed - invalid code', [
                'phone' => $request->phone,
                'provided_code' => $request->otp_code
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Code OTP incorrect',
            ], 422);
        }

        // Clear OTP from user
        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);
        Log::info('[AUTH] OTP cleared from user', ['user_id' => $user->id]);

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;
        Log::info('[AUTH] Sanctum token created', [
            'user_id' => $user->id,
            'phone' => $user->phone
        ]);

        Log::info('[AUTH] OTP verification successful', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'is_new_user' => !$user->is_profile_complete,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'roles' => $user->getRoles(),
                'avatar' => $user->avatar,
                'country' => $user->country,
                'address' => $user->address,
                'is_profile_complete' => (bool) $user->is_profile_complete,
                'preferences' => $user->preferences,
                'referral_code' => $user->referral_code,
                'company_name' => $user->company_name,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'is_new_user' => !$user->is_profile_complete,
        ]);
    }

    /**
     * Login with phone and password (no OTP)
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        Log::info('[AUTH] ========== LOGIN ==========', ['phone' => $request->phone]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            Log::warning('[AUTH] User not found for login', ['phone' => $request->phone]);
            return response()->json([
                'success' => false,
                'message' => 'Numéro de téléphone ou mot de passe incorrect',
            ], 401);
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            Log::warning('[AUTH] Invalid password for login', [
                'user_id' => $user->id,
                'phone' => $request->phone
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Numéro de téléphone ou mot de passe incorrect',
            ], 401);
        }

        Log::info('[AUTH] Login successful', ['user_id' => $user->id, 'phone' => $user->phone]);

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;
        Log::info('[AUTH] Sanctum token created for login', [
            'user_id' => $user->id,
            'phone' => $user->phone
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'roles' => $user->getRoles(),
                'gender' => $user->gender,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'avatar' => $user->avatar,
                'country' => $user->country,
                'address' => $user->address,
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'is_profile_complete' => (bool) $user->is_profile_complete,
                'preferences' => $user->preferences,
                'referral_code' => $user->referral_code,
                'company_name' => $user->company_name,
                'company_logo' => $user->company_logo,
                'total_earnings' => $user->total_earnings,
                'pending_earnings' => $user->pending_earnings,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $user->load('shops');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'roles' => $user->getRoles(),
                'gender' => $user->gender,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'avatar' => $user->avatar,
                'country' => $user->country,
                'address' => $user->address,
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'is_profile_complete' => (bool) $user->is_profile_complete,
                'preferences' => $user->preferences,
                'referral_code' => $user->referral_code,
                'company_name' => $user->company_name,
                'company_logo' => $user->company_logo,
                'total_earnings' => $user->total_earnings,
                'pending_earnings' => $user->pending_earnings,
                'shops' => $user->shops->map(fn($shop) => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'logo' => $shop->logo,
                    'status' => $shop->status,
                ]),
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $request->user()->id,
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();
        $data = $request->only([
            'first_name', 'last_name', 'email', 'gender', 'birth_date',
            'address', 'latitude', 'longitude',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        // Mark profile as complete if required fields are filled
        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            $data['is_profile_complete'] = true;
        }

        $user->update(array_filter($data, fn($v) => $v !== null));

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'roles' => $user->getRoles(),
                'gender' => $user->gender,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'avatar' => $user->avatar,
                'country' => $user->country,
                'address' => $user->address,
                'is_profile_complete' => (bool) $user->is_profile_complete,
                'preferences' => $user->preferences,
                'referral_code' => $user->referral_code,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get user preferences
     */
    public function getPreferences(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'preferences' => $user->preferences ?? [],
        ]);
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'preferences' => 'required|array',
        ]);

        $user = $request->user();
        $user->update(['preferences' => $request->preferences]);

        return response()->json([
            'success' => true,
            'message' => 'Préférences mises à jour',
            'preferences' => $user->preferences,
        ]);
    }

    /**
     * Logout (revoke current token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);
    }
}
