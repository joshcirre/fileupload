<?php

declare(strict_types=1);

use App\Actions\AbortMultipartUploadAction;
use App\Actions\CompleteMultipartUploadAction;
use App\Actions\DownloadShareAction;
use App\Actions\FinalizeUploadAction;
use App\Actions\InitiateMultipartUploadAction;
use App\Actions\SignMultipartPartAction;
use App\Actions\StoreUploadChunkAction;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::middleware('throttle:uploads')->group(function () {
    Route::post('/upload/chunk', [StoreUploadChunkAction::class, 'handle'])->name('upload.chunk');
    Route::post('/upload/finalize', [FinalizeUploadAction::class, 'handle'])->name('upload.finalize');
});

Route::livewire('/direct', 'pages::direct')->name('direct');

Route::middleware('throttle:uploads')->prefix('direct')->name('direct.')->group(function () {
    Route::post('/init', [InitiateMultipartUploadAction::class, 'handle'])->name('init');
    Route::post('/sign', [SignMultipartPartAction::class, 'handle'])->name('sign');
    Route::post('/complete', [CompleteMultipartUploadAction::class, 'handle'])->name('complete');
    Route::post('/abort', [AbortMultipartUploadAction::class, 'handle'])->name('abort');
});

Route::livewire('/s/{share}', 'pages::share')->name('share.show');
Route::get('/s/{share}/download', [DownloadShareAction::class, 'handle'])
    ->middleware('throttle:downloads')
    ->name('share.download');

Route::middleware('auth')->group(function () {
    Route::livewire('/shares', 'pages::shares')->name('shares.index');
    Route::livewire('/profile', 'pages::profile.index')->name('profile.update');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/auth/login', 'pages::auth.login')->name('login');
    Route::livewire('/auth/register', 'pages::auth.register')->name('register');
    Route::livewire('/auth/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/auth/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});
