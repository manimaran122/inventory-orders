<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth-token:'.sha1('tokenuser@example.test|127.0.0.1'));
    }

    #[Test]
    public function it_issues_a_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'tokenuser@example.test',
            'password' => 'correct-horse-battery-staple',
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => 'tokenuser@example.test',
            'password' => 'correct-horse-battery-staple',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name', 'email']]])
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function the_stored_token_is_hashed_not_plain_text(): void
    {
        $user = User::factory()->create([
            'email' => 'tokenuser@example.test',
            'password' => 'secret-password',
        ]);

        $plain = $this->postJson('/api/auth/token', [
            'email' => 'tokenuser@example.test',
            'password' => 'secret-password',
        ])->json('data.token');

        // The plain-text token must never appear in the database.
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $plain]);
        $this->assertSame(1, $user->tokens()->count());
    }

    #[Test]
    public function it_rejects_a_wrong_password_with_a_generic_message(): void
    {
        User::factory()->create([
            'email' => 'tokenuser@example.test',
            'password' => 'the-right-password',
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => 'tokenuser@example.test',
            'password' => 'the-wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');

        // The message must not reveal whether the email exists.
        $this->assertStringNotContainsStringIgnoringCase(
            'password',
            (string) $response->json('errors.email.0'),
        );
    }

    #[Test]
    public function an_unknown_email_and_a_wrong_password_give_the_same_message(): void
    {
        User::factory()->create([
            'email' => 'tokenuser@example.test',
            'password' => 'the-right-password',
        ]);

        $wrongPassword = $this->postJson('/api/auth/token', [
            'email' => 'tokenuser@example.test',
            'password' => 'nope',
        ])->json('errors.email.0');

        RateLimiter::clear('auth-token:'.sha1('ghost@example.test|127.0.0.1'));

        $unknownEmail = $this->postJson('/api/auth/token', [
            'email' => 'ghost@example.test',
            'password' => 'nope',
        ])->json('errors.email.0');

        // Identical wording stops an attacker enumerating valid accounts.
        $this->assertSame($wrongPassword, $unknownEmail);
    }

    #[Test]
    public function it_validates_the_credential_payload(): void
    {
        $this->postJson('/api/auth/token', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function a_valid_token_grants_access_to_protected_routes(): void
    {
        User::factory()->create([
            'email' => 'tokenuser@example.test',
            'password' => 'valid-password',
        ]);

        $token = $this->postJson('/api/auth/token', [
            'email' => 'tokenuser@example.test',
            'password' => 'valid-password',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/products/low-stock')
            ->assertOk();
    }

    #[Test]
    public function a_revoked_token_no_longer_grants_access(): void
    {
        User::factory()->create([
            'email' => 'tokenuser@example.test',
            'password' => 'valid-password',
        ]);

        $token = $this->postJson('/api/auth/token', [
            'email' => 'tokenuser@example.test',
            'password' => 'valid-password',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/auth/token')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The auth guard caches the user it resolved for the current process,
        // so without this the second request would reuse the already-resolved
        // identity and wrongly appear to succeed. A real HTTP request arrives
        // on a fresh process and re-resolves the token from the database;
        // forgetting the user here reproduces that behaviour faithfully
        // instead of testing the cache.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/products/low-stock')
            ->assertUnauthorized();
    }

    #[Test]
    public function it_throttles_repeated_failed_login_attempts(): void
    {
        User::factory()->create([
            'email' => 'tokenuser@example.test',
            'password' => 'the-right-password',
        ]);

        // Five failures are allowed, the sixth is throttled.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/token', [
                'email' => 'tokenuser@example.test',
                'password' => 'wrong',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/token', [
            'email' => 'tokenuser@example.test',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    #[Test]
    public function protected_routes_reject_unauthenticated_requests(): void
    {
        $product = Product::factory()->withStock(5)->create();

        $this->postJson('/api/orders', [
            'customer' => ['name' => 'Anon', 'email' => 'anon@example.test'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnauthorized();

        $this->getJson('/api/customers/orders?email=anon@example.test')->assertUnauthorized();
        $this->getJson('/api/products/low-stock')->assertUnauthorized();

        // An unauthenticated request must not have changed anything.
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function an_invalid_bearer_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/products/low-stock')
            ->assertUnauthorized();
    }
}
