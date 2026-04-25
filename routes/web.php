<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// Redirect root to admin login
Route::get('/', fn() => redirect()->route('admin.login'));

// Admin Auth
Route::get('/admin/login', [AdminController::class, 'loginForm'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.post');

// Admin Protected Routes
Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    });
