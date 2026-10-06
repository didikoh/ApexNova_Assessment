<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_routes_require_authentication_even_without_accept_header(): void
    {
        foreach ([['GET', '/api/products'], ['POST', '/api/products'], ['GET', '/api/products/1'],
            ['PATCH', '/api/products/1'], ['PUT', '/api/products/1'], ['DELETE', '/api/products/1'],
            ['GET', '/api/categories'], ['GET', '/api/suppliers'], ['POST', '/api/logout']] as [$method, $uri]) {
            $this->call($method, $uri)->assertUnauthorized()->assertJsonStructure(['message']);
        }
    }

    public function test_login_issues_a_working_bearer_token_and_logout_revokes_only_that_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $otherToken = $user->createToken('other-device')->accessToken;
        $token = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'secret-password', 'device_name' => 'test',
        ])->assertOk()->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'expires_at']])->json('data.token');

        $this->withToken($token)->getJson('/api/products')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/products')->assertUnauthorized();
    }

    public function test_invalid_credentials_do_not_issue_a_token(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_validates_input_and_rejects_email_control_characters(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/login', ['email' => "a\r\n@example.com", 'password' => 'test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_invalid_and_expired_tokens_are_rejected(): void
    {
        $this->withToken('invalid')->getJson('/api/products')->assertUnauthorized();
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['*'], now()->subMinute());
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/products')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }
}
