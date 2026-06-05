<?php

use App\Models\GalleryEvent;
use App\Models\GalleryPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $eventTitle = '';

    public ?string $eventDate = null;

    public string $eventDescription = '';

    public array $photos = [];

    public ?string $successMessage = null;

    protected function rules(): array
    {
        return [
            'eventTitle' => ['required', 'string', 'max:150'],
            'eventDate' => ['nullable', 'date'],
            'eventDescription' => ['nullable', 'string', 'max:350'],
            'photos' => ['required', 'array', 'min:1', 'max:12'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    protected function messages(): array
    {
        return [
            'eventTitle.required' => 'Escribe el nombre del evento antes de guardar.',
            'eventTitle.max' => 'El nombre del evento no debe superar 150 caracteres.',
            'eventDate.date' => 'La fecha del evento no es válida.',
            'eventDescription.max' => 'La descripción no debe superar 350 caracteres.',
            'photos.required' => 'Selecciona al menos una fotografía.',
            'photos.min' => 'Selecciona al menos una fotografía.',
            'photos.max' => 'Puedes subir hasta 12 fotografías por evento.',
            'photos.*.file' => 'Uno de los archivos seleccionados no es válido.',
            'photos.*.mimes' => 'Solo se permiten imágenes JPG, JPEG, PNG o WEBP.',
            'photos.*.max' => 'Cada imagen debe pesar 20 MB o menos.',
        ];
    }

    public function updatedPhotos(): void
    {
        $this->successMessage = null;
        $this->validateOnly('photos');
    }

    public function saveEvent(): void
    {
        $validated = $this->validate();

        $event = DB::transaction(function () use ($validated): GalleryEvent {
            $event = GalleryEvent::create([
                'title' => trim($validated['eventTitle']),
                'description' => trim((string) ($validated['eventDescription'] ?? '')) ?: null,
                'event_date' => $validated['eventDate'] ?: null,
            ]);

            foreach ($this->photos as $index => $photo) {
                $path = $photo->store('gallery/events/'.$event->id, 'public');

                GalleryPhoto::create([
                    'gallery_event_id' => $event->id,
                    'original_name' => $photo->getClientOriginalName(),
                    'stored_name' => basename($path),
                    'path' => $path,
                    'disk' => 'public',
                    'mime_type' => $photo->getMimeType(),
                    'size' => $photo->getSize(),
                    'sort_order' => $index + 1,
                ]);
            }

            return $event;
        });

        $this->reset('eventTitle', 'eventDate', 'eventDescription', 'photos');
        $this->successMessage = "El evento \"{$event->title}\" se guardó correctamente.";
        $this->resetValidation();
    }

    public function deleteEvent(int $eventId): void
    {
        $event = GalleryEvent::with('photos')->find($eventId);

        if (! $event) {
            return;
        }

        foreach ($event->photos as $photo) {
            Storage::disk($photo->disk)->delete($photo->path);
        }

        $event->delete();
        $this->successMessage = 'Evento eliminado correctamente.';
    }

    public function deletePhoto(int $photoId): void
    {
        $photo = GalleryPhoto::find($photoId);

        if (! $photo) {
            return;
        }

        $event = $photo->event;
        Storage::disk($photo->disk)->delete($photo->path);
        $photo->delete();

        if ($event && $event->photos()->count() === 0) {
            $event->delete();
        }

        $this->successMessage = 'Fotografía eliminada correctamente.';
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }

    public function galleryEvents()
    {
        return GalleryEvent::query()
            ->with(['photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->withCount('photos')
            ->whereHas('photos')
            ->latest('id')
            ->get();
    }
};
?>

<div class="upload-page" data-gallery-manager>
    <header class="brand-header">
        <div class="brand-lockup">
            <img class="brand-logo" src="{{ asset('company-logo-transparent.png') }}" alt="Camino Unidos">
            <p class="brand-kicker"></p>
        </div>

        <nav class="main-nav" aria-label="Navegación principal">
            <a href="{{ route('documents.upload') }}" @class(['is-active' => request()->routeIs('documents.upload')])>Padrón electoral</a>
            <a href="{{ route('organic-structure') }}" @class(['is-active' => request()->routeIs('organic-structure')])>Documento</a>
            <a href="{{ route('gallery.manager') }}" @class(['is-active' => request()->routeIs('gallery.manager')])>Galería</a>
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

    <main class="upload-shell gallery-shell">
        <section class="page-toolbar" aria-labelledby="gallery-title">
            <div>
                <p class="eyebrow">Galería pública</p>
                <h1 id="gallery-title" class="upload-title">Galería de fotos</h1>
                <p class="upload-subtitle">Organiza las fotografías por evento y publícalas en la página principal del movimiento.</p>
            </div>
        </section>

        @if ($successMessage)
            <div class="success-panel floating-message" role="status">{{ $successMessage }}</div>
        @endif

        <section class="upload-card gallery-card" aria-label="Subir evento de galería">
            <div class="upload-main">
                <div class="panel-title-row">
                    <div>
                        <p class="eyebrow">Nuevo evento</p>
                        <h2>Subir fotografías</h2>
                    </div>
                </div>

                <p class="upload-subtitle">Crea un evento con título, fecha opcional y varias imágenes. Cada evento aparecerá agrupado en la página pública.</p>

                <div class="gallery-form-grid">
                    <label class="name-field full-width-field">
                        <span>Título del evento</span>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="eventTitle"
                            maxlength="150"
                            placeholder="Ejemplo: Recorrido territorial en el centro"
                        >
                        @error('eventTitle') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="name-field">
                        <span>Fecha del evento</span>
                        <input
                            type="date"
                            wire:model.live="eventDate"
                        >
                        @error('eventDate') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="name-field">
                        <span>Descripción breve</span>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="eventDescription"
                            maxlength="350"
                            placeholder="Ejemplo: Encuentro con vecinos y recorrido por barrios"
                        >
                        @error('eventDescription') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <label class="dropzone gallery-dropzone" for="gallery-photos">
                    <span>
                        <span class="dropzone-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <path d="m17 8-5-5-5 5"></path>
                                <path d="M12 3v12"></path>
                            </svg>
                        </span>
                        <strong>Arrastra las fotos del evento aquí</strong>
                        <small>También puedes seleccionarlas desde tu equipo</small>
                        <span class="primary-button">Seleccionar imágenes</span>
                    </span>
                </label>

                <input
                    id="gallery-photos"
                    class="file-input"
                    type="file"
                    wire:model="photos"
                    multiple
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                @php
                    $photoErrors = collect($errors->messages())->filter(fn ($messages, $key) => str_starts_with($key, 'photos'));
                @endphp

                @if ($errors->has('photos') || $photoErrors->isNotEmpty())
                    <div class="error-panel" role="alert">
                        Revisa las fotografías seleccionadas.
                        <ul>
                            @foreach ($photoErrors as $messages)
                                @foreach ($messages as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <aside class="upload-side gallery-side" aria-label="Fotografías seleccionadas">
                <div class="files-header">
                    <div>
                        <h2>Previsualización</h2>
                    </div>
                    <span class="files-count">{{ count($photos) }} foto{{ count($photos) === 1 ? '' : 's' }}</span>
                </div>

                @if (count($photos) > 0)
                    <div class="gallery-preview-grid">
                        @foreach ($photos as $photo)
                            <figure class="gallery-preview-item">
                                <img src="{{ $photo->temporaryUrl() }}" alt="Vista previa de {{ $photo->getClientOriginalName() }}">
                                <figcaption>
                                    <strong>{{ pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME) }}</strong>
                                    <span>{{ $this->formatBytes($photo->getSize()) }}</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                @else
                    <p class="empty-state">Las fotografías seleccionadas aparecerán aquí antes de guardarse.</p>
                @endif

                <button class="save-button" type="button" wire:click="saveEvent" wire:loading.attr="disabled" @disabled(count($photos) === 0 || trim($eventTitle) === '')>
                    Guardar galería
                </button>
                <p class="side-note">Cada evento queda publicado con sus fotografías agrupadas y ordenadas.</p>
            </aside>
        </section>

        @php
            $galleryEvents = $this->galleryEvents();
        @endphp

        <section class="data-card gallery-admin-card" aria-labelledby="gallery-events-title">
            <div class="data-header">
                <div>
                    <p class="eyebrow">Eventos publicados</p>
                    <h2 id="gallery-events-title">Galería del movimiento</h2>
                </div>
                <span class="files-count">{{ $galleryEvents->count() }} evento{{ $galleryEvents->count() === 1 ? '' : 's' }}</span>
            </div>

            @if ($galleryEvents->isEmpty())
                <p class="empty-state">Todavía no hay galerías publicadas.</p>
            @else
                <div class="gallery-admin-list">
                    @foreach ($galleryEvents as $event)
                        <article class="gallery-admin-event" wire:key="gallery-event-{{ $event->id }}">
                            <div class="gallery-admin-head">
                                <div>
                                    <span class="document-badge">Evento</span>
                                    <h3>{{ $event->title }}</h3>
                                    <p>
                                        {{ optional($event->event_date ?? $event->created_at)->format('d/m/Y') }}
                                        · {{ $event->photos_count }} foto{{ $event->photos_count === 1 ? '' : 's' }}
                                    </p>
                                    @if ($event->description)
                                        <p class="gallery-event-description">{{ $event->description }}</p>
                                    @endif
                                </div>

                                <button
                                    type="button"
                                    class="pdf-delete-button"
                                    wire:click="deleteEvent({{ $event->id }})"
                                    wire:confirm="¿Eliminar este evento y todas sus fotografías?"
                                    aria-label="Eliminar evento {{ $event->title }}"
                                    title="Eliminar evento"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                        <path d="M6 6l1 14h10l1-14"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="gallery-admin-grid">
                                @foreach ($event->photos as $photo)
                                    <figure class="gallery-admin-photo" wire:key="gallery-photo-{{ $photo->id }}">
                                        <img src="{{ asset('storage/'.$photo->path) }}" alt="{{ $photo->original_name }}">
                                        <button
                                            type="button"
                                            class="gallery-photo-remove"
                                            wire:click="deletePhoto({{ $photo->id }})"
                                            wire:confirm="¿Eliminar esta fotografía?"
                                            aria-label="Eliminar fotografía {{ $photo->original_name }}"
                                            title="Eliminar foto"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M18 6 6 18"></path>
                                                <path d="m6 6 12 12"></path>
                                            </svg>
                                        </button>
                                    </figure>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
</div>
