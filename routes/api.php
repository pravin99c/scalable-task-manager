<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\Api\V1\ProjectMemberController;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // Project and Task Management Routes
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('tasks', TaskController::class);

        // Explicit membership, managed by the project owner.
        Route::get('projects/{project}/members', [ProjectMemberController::class, 'index']);
        Route::post('projects/{project}/members', [ProjectMemberController::class, 'store']);
        Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);

        // Comments
        Route::get('tasks/{task}/comments', [CommentController::class, 'index']);
        Route::post('tasks/{task}/comments', [CommentController::class, 'store']);
    });
});


// Version 2 has a stable data/meta/links envelope and bounded pagination.
// Version 1 remains unchanged for existing clients.
Route::prefix('v2')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('projects', \App\Http\Controllers\Api\V2\ProjectController::class);
    Route::apiResource('tasks', \App\Http\Controllers\Api\V2\TaskController::class);
});
