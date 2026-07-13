<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_a_guest_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Learning User',
            'email' => 'learner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::query()->where('email', 'learner@example.com')->firstOrFail();

        $response->assertRedirect('/notes');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_requires_a_unique_email_and_confirmed_password(): void
    {
        User::factory()->create(['email' => 'used@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'Learning User',
            'email' => 'used@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_a_registered_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.com',
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => 'learner@example.com',
            'password' => 'password123',
        ])->assertRedirect('/notes');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'learner@example.com',
            'password' => 'password123',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'learner@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/notes');

        $this->assertGuest();
    }
}
