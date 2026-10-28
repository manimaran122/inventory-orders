<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This application has no login screen, so there is no 'login'
        // route to redirect an unauthenticated visitor to. Without this,
        // Laravel's Authenticate middleware calls route('login') before
        // the exception handler below runs, throwing RouteNotFoundException
        // which surfaces as a 500 instead of the intended 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Unauthenticated API callers get a clean 401 rather than a redirect to
        // a login page that does not exist for an API.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated. Provide a valid Bearer token.',
                ], JsonResponse::HTTP_UNAUTHORIZED);
            }

            return null;
        });

        // A database error must never reach the client verbatim: the driver
        // message contains table names, column names and often the SQL itself.
        // Laravel already hides this when APP_DEBUG=false, but being explicit
        // means a misconfigured production .env cannot leak the schema.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            report($e);

            return response()->json([
                'message' => 'A database error occurred while processing the request.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        });

        // Catch-all for anything unhandled, so an API consumer always receives
        // JSON (never an HTML error page) and never a stack trace.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Let Laravel handle the exceptions it already renders well
            // (validation 422, model-not-found 404, HTTP exceptions, etc).
            if ($e instanceof HttpExceptionInterface
                || $e instanceof ValidationException) {
                return null;
            }

            report($e);

            // In local/testing the real message is genuinely useful; in
            // production it is withheld.
            $message = config('app.debug')
                ? $e->getMessage()
                : 'An unexpected error occurred. Please try again later.';

            return response()->json([
                'message' => $message,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        });

        // Passwords and tokens must never be written to logs or flashed to the
        // session, even in a stack trace or a failed-validation redirect.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'token',
            'api_token',
        ]);
    })->create();
