<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganicStructureDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::livewire('/', 'upload-documents')
    ->middleware(['auth', 'admin'])
    ->name('documents.upload');

Route::livewire('/estructura-organica', 'organic-structure')
    ->middleware(['auth', 'admin'])
    ->name('organic-structure');

Route::post('/estructura-organica/documentos', [OrganicStructureDocumentController::class, 'store'])
    ->middleware(['auth', 'admin'])
    ->name('organic-structure.documents.store');
