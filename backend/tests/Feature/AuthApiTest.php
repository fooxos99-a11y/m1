<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receive_api_token(): void
    {
        User::query()->create([
            'full_name' => 'Admin User',
            'role' => 'admin',
            'login_code' => '1483',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->postJson('/api/auth/login', [
            'login_code' => '1483',
            'password' => 'secret-pass',
        ])
            ->assertOk()
            ->assertJsonPath('user.loginCode', '1483')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'role', 'loginCode']]);
    }

    public function test_user_route_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'login_code' => '5001',
            'role' => 'admin',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('loginCode', '5001');
    }

    public function test_user_cannot_log_in_with_invalid_password(): void
    {
        User::query()->create([
            'full_name' => 'Admin User',
            'role' => 'admin',
            'login_code' => '1483',
            'password' => Hash::make('secret-pass'),
        ]);

        $this->postJson('/api/auth/login', [
            'login_code' => '1483',
            'password' => 'wrong-pass',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login_code']);
    }

    public function test_subdirectory_api_guest_receives_json_unauthenticated_response(): void
    {
        $this->withServerVariables([
            'REQUEST_URI' => '/momars/api/dashboard/snapshot',
        ])
            ->get('/api/dashboard/snapshot')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
