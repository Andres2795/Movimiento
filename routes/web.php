<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientPageController;
use App\Http\Controllers\MovementJoinRequestController;
use App\Http\Controllers\OrganicStructureDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ClientPageController::class, 'index'])->name('client.home');
Route::redirect('/cliente', '/');
Route::redirect('/transparencia', '/#transparencia')->name('client.transparency');
Route::post('/unirse', [MovementJoinRequestController::class, 'store'])->name('movement.join.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::livewire('/admin', 'upload-documents')
    ->middleware(['auth', 'admin'])
    ->name('documents.upload');

Route::livewire('/estructura-organica', 'organic-structure')
    ->middleware(['auth', 'admin'])
    ->name('organic-structure');

Route::livewire('/pagina-publica', 'public-page-settings')
    ->middleware(['auth', 'admin'])
    ->name('public-page.settings');

Route::livewire('/galeria', 'gallery-manager')
    ->middleware(['auth', 'admin'])
    ->name('gallery.manager');

Route::livewire('/solicitudes', 'join-requests')
    ->middleware(['auth', 'admin'])
    ->name('join-requests');

Route::post('/estructura-organica/documentos', [OrganicStructureDocumentController::class, 'store'])
    ->middleware(['auth', 'admin'])
    ->name('organic-structure.documents.store');
