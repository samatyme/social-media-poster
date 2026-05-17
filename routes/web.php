<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth pages
Route::get('/login',    fn() => Inertia::render('Auth/Login'))->name('login');
Route::get('/register', fn() => Inertia::render('Auth/Register'))->name('register');

// Protected app pages (auth handled on frontend; API uses Sanctum)
Route::get('/dashboard',              fn() => Inertia::render('Dashboard/Index'))->name('dashboard');
Route::get('/posts',                  fn() => Inertia::render('Posts/Index'))->name('posts.index');
Route::get('/posts/create',           fn() => Inertia::render('Posts/Compose'))->name('posts.create');
Route::get('/posts/{postId}/edit',    fn($postId) => Inertia::render('Posts/Compose', ['postId' => $postId]))->name('posts.edit');
Route::get('/posts/{postId}',         fn($postId) => Inertia::render('Posts/Show',    ['postId' => $postId]))->name('posts.show');
Route::get('/calendar',               fn() => Inertia::render('Calendar/Index'))->name('calendar');
Route::get('/media',                  fn() => Inertia::render('Media/Index'))->name('media');
Route::get('/accounts',               fn() => Inertia::render('Accounts/Index'))->name('accounts');
Route::get('/team',                   fn() => Inertia::render('Team/Index'))->name('team');
Route::get('/settings',               fn() => Inertia::render('Settings/Index'))->name('settings');

// Default redirect
Route::get('/', fn() => redirect('/dashboard'));
