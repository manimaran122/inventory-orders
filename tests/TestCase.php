<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate the test as a Sanctum token holder.
     *
     * Sanctum::actingAs sets the authenticated user for guard-based checks
     * without performing a real token exchange, which keeps individual tests
     * focused on the endpoint under test rather than on login mechanics. The
     * token exchange itself is covered separately in AuthTokenTest.
     */
    protected function actingAsApiUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * Standard JSON headers for API requests.
     *
     * @return array<string, string>
     */
    protected function jsonHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
