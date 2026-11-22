<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Keep legacy v1 exception responses intact. v2 uses one JSON envelope.
        $exceptions->render(function (ValidationException $e, Request $request) {
            return $request->is('api/v2/*')
                ? ApiResponse::error('Validation failed.', 422, $e->errors())
                : null;
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return $request->is('api/v2/*')
                ? ApiResponse::error('This action is unauthorized.', $e->status() ?: 403)
                : null;
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return $request->is('api/v2/*')
                ? ApiResponse::error('Unauthenticated.', 401)
                : null;
        });
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return $request->is('api/v2/*')
                ? ApiResponse::error('Resource not found.', 404)
                : null;
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return $request->is('api/v2/*')
                ? ApiResponse::error('Resource not found.', 404)
                : null;
        });
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            return $request->is('api/v2/*')
                ? ApiResponse::error('Too many requests.', 429)
                : null;
        });
    })->create();
