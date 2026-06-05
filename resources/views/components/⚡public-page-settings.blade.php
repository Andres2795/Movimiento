<?php

use App\Models\PublicPageSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $heroImage = null;

    public ?string $successMessage = null;

    protected function rules(): array
    {
        return [
            'heroImage' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    protected function messages(): array
    {
        return [
            'heroImage.required' => 'Selecciona una imagen para la página pública.',
            'heroImage.image' => 'El archivo debe ser una imagen válida.',
            'heroImage.mimes' => 'Solo se permiten imágenes JPG, JPEG, PNG o WEBP.',
            'heroImage.max' => 'La imagen debe pesar 20 MB o menos.',
        ];
    }

    public function saveHeroImage(): void
    {
        $this->validate();

        $setting = PublicPageSetting::current();
        $oldPath = $setting->hero_image_path;
        $path = $this->heroImage->store('public-page', 'public');

        $setting->update([
            'hero_image_path' => $path,
            'hero_image_original_name' => $this->heroImage->getClientOriginalName(),
        ]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->reset('heroImage');
        $this->successMessage = 'Imagen principal actualizada correctamente.';
    }

    public function setting(): PublicPageSetting
    {
        return PublicPageSetting::current();
    }
};
?>

<div class="upload-page" data-upload-documents>
    <header class="brand-header">
        <div class="brand-lockup">
            <img class="brand-logo" src="{{ asset('company-logo-transparent.png') }}" alt="Camino Unidos">
            <p class="brand-kicker"></p>
        </div>

        <nav class="main-nav" aria-label="Navegación principal">
            <a href="{{ route('documents.upload') }}" @class(['is-active' => request()->routeIs('documents.upload')])>Padrón electoral</a>
            <a href="{{ route('organic-structure') }}" @class(['is-active' => request()->routeIs('organic-structure')])>Documento</a>
            <a href="{{ route('public-page.settings') }}" @class(['is-active' => request()->routeIs('public-page.settings')])>Banner</a>
            <a href="{{ route('join-requests') }}" @class(['is-active' => request()->routeIs('join-requests')])>Solicitudes</a>
        </nav>

        <span class="status-pill">
            <span class="status-dot" aria-hidden="true"></span>
            {{ auth()->user()?->name }}
        </span>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout-button" type="submit">Cerrar sesión</button>
        </form>
    </header>

    <main class="upload-shell">
        <section class="page-toolbar" aria-labelledby="public-page-title">
            <div>
                <p class="eyebrow">Página cliente</p>
                <h1 id="public-page-title" class="upload-title">Banner</h1>
                <p class="upload-subtitle">Administra la imagen principal que aparece junto al texto inicial del movimiento.</p>
            </div>
        </section>

        @if ($successMessage)
            <div class="success-panel floating-message" role="status">{{ $successMessage }}</div>
        @endif

        @php
            $setting = $this->setting();
        @endphp

        <section class="upload-card public-page-card" aria-label="Subir imagen principal">
            <div class="upload-main">
                <div class="panel-title-row">
                    <div>
                        <p class="eyebrow">Imagen principal</p>
                        <h2>Banner de la página pública</h2>
                    </div>
                </div>

                <p class="upload-subtitle">Esta imagen se mostrará en la sección donde aparece “Movimiento Caminemos Unidos”.</p>

                <label class="dropzone compact-dropzone" for="hero-image">
                    <span>
                        <span class="dropzone-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <path d="m17 8-5-5-5 5"></path>
                                <path d="M12 3v12"></path>
                            </svg>
                        </span>
                        <strong>Selecciona una imagen</strong>
                        <small>JPG, JPEG, PNG o WEBP. Máximo 20 MB.</small>
                        <span class="primary-button">Elegir imagen</span>
                    </span>
                </label>

                <input
                    id="hero-image"
                    class="file-input"
                    type="file"
                    wire:model="heroImage"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                @error('heroImage')
                    <div class="error-panel" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <aside class="upload-side" aria-label="Vista previa de imagen principal">
                <div class="files-header">
                    <h2>Vista previa</h2>
                </div>

                @if ($heroImage)
                    <img class="hero-admin-preview" src="{{ $heroImage->temporaryUrl() }}" alt="Vista previa de la nueva imagen">
                @elseif ($setting->hero_image_path)
                    <img class="hero-admin-preview" src="{{ asset('storage/'.$setting->hero_image_path) }}" alt="Imagen actual de la página pública">
                    <p class="side-note">{{ $setting->hero_image_original_name }}</p>
                @else
                    <p class="empty-state">Todavía no hay imagen principal configurada.</p>
                @endif

                <button class="save-button" type="button" wire:click="saveHeroImage" wire:loading.attr="disabled" @disabled(! $heroImage)>
                    Guardar imagen
                </button>
            </aside>
        </section>
    </main>
</div>
