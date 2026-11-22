<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// Redirect root to admin login
Route::get('/', fn() => redirect()->route('admin.login'));

// Admin Auth
Route::get('/admin/login', [AdminController::class, 'loginForm'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.post');

// Admin Protected Routes
Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class, \App\Http\Middleware\EnsureSessionVersion::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        
        // User Management
        Route::get('/users', [AdminController::class, 'userIndex'])->name('users.index');
        Route::get('/users/create', [AdminController::class, 'userCreate'])->name('users.create');
        Route::post('/users', [AdminController::class, 'userStore'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'userEdit'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'userUpdate'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'userDestroy'])->name('users.destroy');

        // Role Management
        Route::get('/roles', [AdminController::class, 'roleIndex'])->name('roles.index');
        Route::get('/roles/create', [AdminController::class, 'roleCreate'])->name('roles.create');
        Route::post('/roles', [AdminController::class, 'roleStore'])->name('roles.store');
        Route::get('/roles/{role}/edit', [AdminController::class, 'roleEdit'])->name('roles.edit');
        Route::put('/roles/{role}', [AdminController::class, 'roleUpdate'])->name('roles.update');
        Route::delete('/roles/{role}', [AdminController::class, 'roleDestroy'])->name('roles.destroy');

        // Project Management
        Route::get('/projects', [AdminController::class, 'projectIndex'])->name('projects.index');
        Route::get('/projects/create', [AdminController::class, 'projectCreate'])->name('projects.create');
        Route::post('/projects', [AdminController::class, 'projectStore'])->name('projects.store');
        Route::get('/projects/{project}/edit', [AdminController::class, 'projectEdit'])->name('projects.edit');
        Route::put('/projects/{project}', [AdminController::class, 'projectUpdate'])->name('projects.update');
        Route::delete('/projects/{project}', [AdminController::class, 'projectDestroy'])->name('projects.destroy');

        // Task Management
        Route::get('/tasks', [AdminController::class, 'taskIndex'])->name('tasks.index');
        Route::get('/tasks/create', [AdminController::class, 'taskCreate'])->name('tasks.create');
        Route::post('/tasks', [AdminController::class, 'taskStore'])->name('tasks.store');
        Route::get('/tasks/{task}/edit', [AdminController::class, 'taskEdit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [AdminController::class, 'taskUpdate'])->name('tasks.update');
        Route::delete('/tasks/{task}', [AdminController::class, 'taskDestroy'])->name('tasks.destroy');
        
        Route::get('/comments', [AdminController::class, 'comments'])->name('comments.index');
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs.index');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings.index');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');

        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    });


// Non-admin TaskForge workspace. The existing admin panel is unchanged.
// All JSON auth actions use Laravel's web session and CSRF protection.
Route::prefix('app/api')->middleware('throttle:60,1')->group(function () {
    Route::get('session', [\App\Http\Controllers\TaskForgeSessionController::class, 'session']);
    Route::post('login', [\App\Http\Controllers\TaskForgeSessionController::class, 'login'])->middleware('throttle:6,1');
    Route::post('register', [\App\Http\Controllers\TaskForgeSessionController::class, 'register'])->middleware('throttle:6,1');
    Route::post('logout', [\App\Http\Controllers\TaskForgeSessionController::class, 'logout'])->middleware('auth');
});

Route::get('/app', fn () => view('taskforge'))->name('taskforge.app');
Route::get('/app/{path}', fn () => view('taskforge'))
    ->where('path', '^(?!api(?:/|$)).*')
    ->name('taskforge.fallback');
