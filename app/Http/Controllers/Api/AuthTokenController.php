<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    /**
     * POST /api/auth/token
     *
     * Exchanges credentials for a Sanctum personal access token.
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $email = mb_strtolower(trim((string) $request->validated('email')));

        // Throttle by email + IP so this endpoint cannot be used to brute-force
        // a password. The route also carries a global throttle.
        $throttleKey = 'auth-token:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, maxAttempts: 5)) {
            return response()->json([
                'message' => 'Too many login attempts. Please try again in '
                    .RateLimiter::availableIn($throttleKey).' seconds.',
            ], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $user = User::query()->where('email', $email)->first();

        // Hash::check is called against a dummy hash when the user is missing
        // so that a wrong email and a wrong password take a comparable amount
        // of time, avoiding a timing side-channel that reveals which emails
        // are registered.
        $passwordMatches = $user !== null
            ? Hash::check((string) $request->validated('password'), $user->password)
            : Hash::check((string) $request->validated('password'), self::DUMMY_HASH);

        if ($user === null || ! $passwordMatches) {
            RateLimiter::hit($throttleKey, decaySeconds: 60);

            // One generic message for both cases: never reveal whether the
            // email exists or only the password was wrong.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Issue a fresh token. The plain-text value is shown exactly once;
        // only a hash is stored.
        $token = $user->createToken($request->deviceName());

        return response()->json([
            'message' => 'Token issued.',
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * DELETE /api/auth/token
     *
     * Revokes the token used to make this request.
     */
    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * A valid bcrypt hash of a value no user has, used only to equalise timing.
     */
    private const string DUMMY_HASH = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
}
