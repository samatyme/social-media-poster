<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth pages
Route::get('/login',          fn() => Inertia::render('Auth/Login'))->name('login');
Route::get('/register',       fn() => Inertia::render('Auth/Register'))->name('register');
Route::get('/reset-password', fn() => Inertia::render('Auth/ResetPassword'))->name('password.reset');

// Protected app pages (auth handled on frontend; API uses Sanctum)
Route::get('/dashboard',           fn() => Inertia::render('Dashboard/Index'))->name('dashboard');
Route::get('/posts',               fn() => Inertia::render('Posts/Index'))->name('web.posts');
Route::get('/posts/create',        fn() => Inertia::render('Posts/Compose'))->name('web.posts.create');
Route::get('/posts/{postId}/edit', fn($postId) => Inertia::render('Posts/Compose', ['postId' => $postId]))->name('web.posts.edit');
Route::get('/posts/{postId}',      fn($postId) => Inertia::render('Posts/Show',    ['postId' => $postId]))->name('web.posts.show');
Route::get('/calendar',            fn() => Inertia::render('Calendar/Index'))->name('web.calendar');
Route::get('/media',               fn() => Inertia::render('Media/Index'))->name('web.media');
Route::get('/accounts',            fn() => Inertia::render('Accounts/Index'))->name('web.accounts');
Route::get('/team',                fn() => Inertia::render('Team/Index'))->name('web.team');
Route::get('/settings',            fn() => Inertia::render('Settings/Index'))->name('web.settings');

// Default redirect
Route::get('/', fn() => redirect('/dashboard'));
