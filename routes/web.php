<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('notes.index');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/notes/ai-search', [NoteController::class, 'vectorSearch'])->name('notes.ai-search');
Route::get('/notes/ai-search/explain', [NoteController::class, 'explainVectorSearch'])->name('notes.ai-search.explain');
Route::get('/notes/ai-search/compare', [NoteController::class, 'compareVectorSearch'])->name('notes.ai-search.compare');
Route::resource('notes', NoteController::class)->only('index');

Route::middleware('auth')->group(function (): void {
    Route::resource('notes', NoteController::class)->only([
        'create',
        'store',
        'edit',
        'update',
        'destroy',
    ]);
});

Route::resource('notes', NoteController::class)->only('show');
