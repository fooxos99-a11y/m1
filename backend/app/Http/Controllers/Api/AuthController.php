<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login_code' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('login_code', $credentials['login_code'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password ?? '')) {
            throw ValidationException::withMessages([
                'login_code' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

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