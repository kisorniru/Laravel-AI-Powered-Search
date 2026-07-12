<?php

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('notes.index');
});

Route::get('/notes/ai-search', [NoteController::class, 'vectorSearch'])->name('notes.ai-search');
Route::resource('notes', NoteController::class);
