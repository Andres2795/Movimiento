<?php

use App\Models\OrganicStructureDocument;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithFileUploads;

    public array $documents = [];

    public array $queuedFiles = [];

    public ?int $selectedDocumentId = null;

    public ?string $successMessage = null;

    public string $documentSearch = '';

    public bool $showUploadPanel = false;

    public ?int $editingDocumentId = null;

    public array $editingDocumentNames = [];

    protected function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf', 'max:20480'],
        ];
    }

    protected function messages(): array
    {
        return [
            'documents.required' => 'Selecciona al menos un PDF de estructura orgánica.',
            'documents.*.mimes' => 'Solo se permiten documentos PDF.',
            'documents.*.max' => 'Cada PDF debe pesar 20 MB o menos.',
            'documents.*.file' => 'Uno de los elementos seleccionados no es un archivo válido.',
        ];
    }

    public function mount(): void
    {
        $this->successMessage = session('organic_success');
        $this->showUploadPanel = session()->has('errors');
        $this->selectedDocumentId = session('selected_document_id')
            ?? OrganicStructureDocument::latest('id')->value('id');
    }

    public function updatedDocuments(): void
    {
        $this->successMessage = null;
        $this->validate();

        $this->queuedFiles = collect($this->documents)
            ->map(fn ($document) => [
                'name' => $document->getClientOriginalName(),
                'size' => $document->getSize(),
                'extension' => strtolower($document->getClientOriginalExtension()),
                'progress' => 100,
            ])
            ->values()
            ->all();
    }

    public function removeFile(int $index): void
    {
        if (! array_key_exists($index, $this->documents)) {
            return;
        }

        unset($this->documents[$index], $this->queuedFiles[$index]);

        $this->documents = array_values($this->documents);
        $this->queuedFiles = array_values($this->queuedFiles);
        $this->successMessage = null;
        $this->resetErrorBag();
    }

    public function saveDocuments(): void
    {
        $this->validate();

        foreach ($this->documents as $document) {
            $path = $document->store('organic-structure', 'public');

            $record = OrganicStructureDocument::create([
                'title' => pathinfo($document->getClientOriginalName(), PATHINFO_FILENAME),
                'original_name' => $document->getClientOriginalName(),
                'stored_name' => basename($path),
                'path' => $path,
                'disk' => 'public',
                'mime_type' => $document->getMimeType(),
                'size' => $document->getSize(),
            ]);

            $this->selectedDocumentId = $record->id;
        }

        $count = count($this->documents);

        $this->reset('documents', 'queuedFiles');
        $this->showUploadPanel = false;
        $this->successMessage = $count === 1
            ? 'PDF guardado correctamente.'
            : "{$count} PDFs guardados correctamente.";
    }

    public function openUploadPanel(): void
    {
        $this->showUploadPanel = true;
    }

    public function closeUploadPanel(): void
    {
        $this->showUploadPanel = false;
        $this->reset('documents', 'queuedFiles');
        $this->resetErrorBag();
    }

    public function selectDocument(int $documentId): void
    {
        $this->selectedDocumentId = $documentId;
    }

    public function deleteDocument(int $documentId): void
    {
        $document = OrganicStructureDocument::find($documentId);

        if (! $document) {
            return;
        }

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        if ($this->selectedDocumentId === $documentId) {
            $this->selectedDocumentId = OrganicStructureDocument::latest('id')->value('id');
        }

        $this->successMessage = 'PDF eliminado correctamente.';
    }

    public function editDocumentName(int $documentId): void
    {
        $document = OrganicStructureDocument::find($documentId);

        if (! $document) {
            return;
        }

        $this->editingDocumentId = $documentId;
        $this->editingDocumentNames[$documentId] = $document->title;
        $this->resetErrorBag("editingDocumentNames.{$documentId}");
    }

    public function cancelDocumentNameEdit(): void
    {
        $this->editingDocumentId = null;
        $this->resetErrorBag();
    }

    public function saveDocumentName(int $documentId): void
    {
        $document = OrganicStructureDocument::find($documentId);

        if (! $document) {
            return;
        }

        $name = trim((string) ($this->editingDocumentNames[$documentId] ?? $document->title));

        if ($name === '') {
            $this->addError("editingDocumentNames.{$documentId}", 'Ingresa un nombre público para el PDF.');
            return;
        }

        if (mb_strlen($name) > 150) {
            $this->addError("editingDocumentNames.{$documentId}", 'El nombre público no debe superar 150 caracteres.');
            return;
        }

        $document->update(['title' => $name]);
        $this->editingDocumentId = null;
        $this->successMessage = 'Nombre del PDF actualizado correctamente.';
    }

    public function clearDocumentSearch(): void
    {
        $this->documentSearch = '';
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

    public function documentsList()
    {
        $query = OrganicStructureDocument::query()->latest('id');
        $search = trim($this->documentSearch);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function selectedDocument(): ?OrganicStructureDocument
    {
        if ($this->selectedDocumentId === null) {
            return null;
        }

        return OrganicStructureDocument::find($this->selectedDocumentId);
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
            <a href="{{ route('documents.upload') }}" @class(['is-active' => request()->routeIs('documents.upload')])>Padrón</a>
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

    <main class="upload-shell organic-shell">
        <section class="page-toolbar" aria-labelledby="organic-title">
            <div>
                <p class="eyebrow">Organización interna</p>
                <h1 id="organic-title" class="upload-title">Documento</h1>
                <p class="upload-subtitle">Visualiza los PDFs institucionales del movimiento.</p>
            </div>

            <button class="primary-button toolbar-button" type="button" wire:click="openUploadPanel">
                Subir archivo
            </button>
        </section>

        @if ($showUploadPanel)
            <form class="upload-card organic-card" action="{{ route('organic-structure.documents.store') }}" method="POST" enctype="multipart/form-data" data-upload-form aria-label="Subir PDFs de estructura orgánica">
                @csrf
                <div class="upload-main">
                    <div class="panel-title-row">
                        <div>
                            <p class="eyebrow">Carga de PDFs</p>
                            <h2>Subir documentos</h2>
                        </div>
                        <button class="remove-button" type="button" wire:click="closeUploadPanel" aria-label="Cerrar panel de subida">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="upload-subtitle">Selecciona uno o varios PDFs para agregarlos a la biblioteca de estructura orgánica.</p>

                    <label class="name-field" for="organic-public-name">
                        <span>Nombre público del documento</span>
                        <input
                            id="organic-public-name"
                            type="text"
                            name="document_name"
                            maxlength="150"
                            placeholder="Ejemplo: Régimen orgánico oficial"
                        >
                    </label>

                <label class="dropzone compact-dropzone" data-dropzone for="organic-documents">
                    <span>
                        <span class="dropzone-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 16V8"></path>
                                <path d="m8 12 4-4 4 4"></path>
                                <path d="M20 16.5a4.5 4.5 0 0 0-2.9-7.94 6 6 0 0 0-11.2 1.8A3.8 3.8 0 0 0 6 18h13"></path>
                            </svg>
                        </span>
                        <strong>Subir PDFs institucionales</strong>
                        <small>Selecciona uno o varios documentos PDF</small>
                        <span class="primary-button">Seleccionar PDFs</span>
                    </span>
                </label>

                <input
                    id="organic-documents"
                    class="file-input"
                    type="file"
                    data-file-input
                    name="documents[]"
                    multiple
                    accept=".pdf,application/pdf"
                >

                @if ($errors->any())
                    <div class="error-panel" role="alert">
                        Revisa los PDFs seleccionados.
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($successMessage)
                    <div class="success-panel" role="status">{{ $successMessage }}</div>
                @endif
                </div>

                <aside class="upload-side" aria-label="PDFs seleccionados">
                    <div class="files-header">
                        <h2>Archivos para guardar</h2>
                        <span class="files-count">{{ count($queuedFiles) }} PDF{{ count($queuedFiles) === 1 ? '' : 's' }}</span>
                    </div>

                    <ul class="pending-list" data-pending-list wire:ignore></ul>

                    <p class="empty-state" data-empty-state>Los PDFs seleccionados aparecerán aquí antes de guardarse.</p>

                    <button class="save-button" type="submit" data-submit-button disabled>
                        Guardar PDFs
                    </button>
                    <p class="side-note">Los PDFs guardados quedan disponibles para visualizarse desde el listado.</p>
                </aside>
            </form>
        @endif

        @if ($successMessage && ! $showUploadPanel)
            <div class="success-panel floating-message" role="status">{{ $successMessage }}</div>
        @endif

        @php
            $organicDocuments = $this->documentsList();
            $selectedDocument = $this->selectedDocument();
        @endphp

        <section class="pdf-workspace" aria-label="Visor de estructura orgánica">
            <aside class="pdf-library">
                <div class="data-header">
                    <div>
                        <p class="eyebrow">Biblioteca PDF</p>
                        <h2>Documentos</h2>
                    </div>
                    <span class="files-count">{{ $organicDocuments->count() }}</span>
                </div>

                <div class="table-tools">
                    <label class="search-field" for="organic-search">
                        <span>Buscar PDF</span>
                        <input
                            id="organic-search"
                            type="search"
                            wire:model.live.debounce.350ms="documentSearch"
                            placeholder="Buscar por nombre..."
                        >
                    </label>

                    @if ($documentSearch !== '')
                        <button class="secondary-button" type="button" wire:click="clearDocumentSearch">Limpiar</button>
                    @endif
                </div>

                @if ($organicDocuments->isEmpty())
                    <p class="empty-state">Todavía no hay PDFs registrados.</p>
                @else
                    <ul class="pdf-list">
                        @foreach ($organicDocuments as $document)
                            <li class="pdf-list-row">
                                <button
                                    type="button"
                                    wire:click="selectDocument({{ $document->id }})"
                                    @class(['pdf-list-item', 'is-selected' => $selectedDocumentId === $document->id])
                                >
                                    <span class="file-icon">PDF</span>
                                    <span class="file-meta">
                                        <span class="file-name">{{ $document->title }}</span>
                                        <span class="file-size">{{ $this->formatBytes($document->size) }} · {{ $document->created_at->format('d/m/Y') }}</span>
                                    </span>
                                </button>
                                <div class="pdf-row-actions">
                                    <button
                                        type="button"
                                        class="pdf-icon-button"
                                        wire:click="editDocumentName({{ $document->id }})"
                                        aria-label="Cambiar nombre de {{ $document->original_name }}"
                                        title="Cambiar nombre"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="pdf-delete-button"
                                        wire:click="deleteDocument({{ $document->id }})"
                                        wire:confirm="¿Eliminar este PDF?"
                                        aria-label="Eliminar {{ $document->original_name }}"
                                        title="Eliminar PDF"
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
                                @if ($editingDocumentId === $document->id)
                                    <div class="pdf-name-editor">
                                        <label class="name-field compact-name-field">
                                            <span>Nombre público</span>
                                            <input
                                                type="text"
                                                wire:model.defer="editingDocumentNames.{{ $document->id }}"
                                                maxlength="150"
                                            >
                                        </label>
                                        <div class="pdf-name-actions">
                                            <button class="secondary-button" type="button" wire:click="saveDocumentName({{ $document->id }})">
                                                Guardar
                                            </button>
                                            <button class="secondary-button ghost-button" type="button" wire:click="cancelDocumentNameEdit">
                                                Cancelar
                                            </button>
                                        </div>
                                        @error("editingDocumentNames.{$document->id}")
                                            <span class="field-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </aside>

            <section class="pdf-viewer-card">
                @if ($selectedDocument)
                    <div class="pdf-viewer-header">
                        <div>
                            <p class="eyebrow">Visualización</p>
                            <h2>{{ $selectedDocument->title }}</h2>
                        </div>
                        <a class="secondary-button" href="{{ asset('storage/'.$selectedDocument->path) }}" target="_blank" rel="noopener">Abrir PDF</a>
                    </div>
                    <iframe
                        class="pdf-viewer"
                        src="{{ asset('storage/'.$selectedDocument->path) }}#toolbar=1&navpanes=0"
                        title="PDF: {{ $selectedDocument->title }}"
                    ></iframe>
                @else
                    <p class="empty-state">Selecciona un PDF del listado para visualizar la estructura orgánica.</p>
                @endif
            </section>
        </section>
    </main>
</div>
