<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoginCodeAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_authenticate_with_login_code_and_password(): void
    {
        $user = User::query()->create([
            'full_name' => 'Admin User',
            'role' => 'admin',
            'login_code' => '1483',
            'password' => Hash::make('secret-pass'),
        ]);

        $response = $this->postJson('/login', [
            'login_code' => '1483',
            'password' => 'secret-pass',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        User::query()->create([
            'full_name' => 'Admin User',
            'role' => 'admin',
            'login_code' => '1483',
            'password' => Hash::make('secret-pass'),
        ]);

        $response = $this->from('/login')->postJson('/login', [
            'login_code' => '1483',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_student_created_via_students_api_can_authenticate_with_login_code(): void
    {
        Sanctum::actingAs(User::query()->create([
            'full_name' => 'Admin User',
            'role' => 'admin',
            'login_code' => '9000',
            'password' => Hash::make('9000'),
        ]));

        $this->postJson('/api/students', [
            'name' => 'طالب دخول',
            'loginId' => '123',
            'branchId' => 'male',
            'note' => '',
        ])->assertCreated();

        $response = $this->postJson('/api/auth/login', [
            'login_code' => '123',
            'password' => '123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.loginCode', '123');
    }
}