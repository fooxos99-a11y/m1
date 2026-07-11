<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login_code' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $rateLimitKey = Str::lower(trim((string) $credentials['login_code'])).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'message' => 'محاولات دخول كثيرة. حاول مرة أخرى بعد قليل.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = User::query()->where('login_code', $credentials['login_code'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password ?? '')) {
            RateLimiter::hit($rateLimitKey, 60);

            throw ValidationException::withMessages([
                'login_code' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($this->serializeUser($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, string>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'role' => $user->role,
            'loginCode' => $user->login_code ?? '',
        ];
    }
}
