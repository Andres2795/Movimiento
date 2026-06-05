<?php

use App\Models\PadronRecord;
use App\Models\UploadedDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

new class extends Component
{
    use WithFileUploads;

    public array $documents = [];

    public array $queuedFiles = [];

    public ?string $successMessage = null;

    public bool $showUploadPanel = false;

    public bool $replaceExistingPadron = false;

    public string $publicName = '';

    public array $editingDocumentNames = [];

    public int $padronPage = 1;

    public int $padronPerPage = 10;

    public string $padronSearch = '';

    protected function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:xls,xlsx', 'max:20480'],
            'publicName' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function messages(): array
    {
        return [
            'documents.required' => 'Selecciona al menos un archivo de padrón.',
            'documents.*.mimes' => 'Solo se permiten archivos XLS o XLSX.',
            'documents.*.max' => 'Cada archivo debe pesar 20 MB o menos.',
            'documents.*.file' => 'Uno de los elementos seleccionados no es un archivo válido.',
            'publicName.max' => 'El nombre público no debe superar 150 caracteres.',
        ];
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

        $importedRecords = 0;
        $count = count($this->documents);
        $replacedPadron = $this->replaceExistingPadron;

        DB::transaction(function () use (&$importedRecords): void {
            if ($this->replaceExistingPadron) {
                $this->deleteCurrentPadronRecords();
            }

            foreach ($this->documents as $document) {
                $path = $document->store('padron', 'public');

                $uploadedDocument = UploadedDocument::create([
                    'original_name' => $document->getClientOriginalName(),
                    'public_name' => $this->publicDocumentName($document->getClientOriginalName()),
                    'stored_name' => basename($path),
                    'path' => $path,
                    'disk' => 'public',
                    'mime_type' => $document->getMimeType(),
                    'extension' => strtolower($document->getClientOriginalExtension()),
                    'size' => $document->getSize(),
                ]);

                $importedRecords += $this->importPadronRows($document->getRealPath(), $uploadedDocument);
            }
        });

        $this->reset('documents', 'queuedFiles', 'publicName');
        $this->showUploadPanel = false;
        $this->padronPage = 1;
        $this->replaceExistingPadron = false;
        $this->successMessage = $count === 1
            ? ($replacedPadron ? 'Padrón actualizado correctamente.' : 'Archivo de padrón guardado correctamente.')
            : ($replacedPadron ? "{$count} archivos de padrón actualizados correctamente." : "{$count} archivos de padrón guardados correctamente.");

        if ($importedRecords > 0) {
            $this->successMessage .= $replacedPadron
                ? " Se reemplazó el padrón e importaron {$importedRecords} registros."
                : " Se importaron {$importedRecords} registros del padrón.";
        }
    }

    public function openUploadPanel(): void
    {
        $this->showUploadPanel = true;
    }

    public function closeUploadPanel(): void
    {
        $this->showUploadPanel = false;
        $this->reset('documents', 'queuedFiles');
        $this->replaceExistingPadron = false;
        $this->publicName = '';
        $this->resetErrorBag();
    }

    public function saveDocumentName(int $documentId): void
    {
        $document = UploadedDocument::find($documentId);

        if (! $document) {
            return;
        }

        $name = trim((string) ($this->editingDocumentNames[$documentId] ?? $document->public_name ?? $document->original_name));

        if ($name === '') {
            $this->addError("editingDocumentNames.{$documentId}", 'Ingresa un nombre público para el documento.');
            return;
        }

        if (mb_strlen($name) > 150) {
            $this->addError("editingDocumentNames.{$documentId}", 'El nombre público no debe superar 150 caracteres.');
            return;
        }

        $document->update(['public_name' => $name]);
        $this->editingDocumentNames[$documentId] = $name;
        $this->successMessage = 'Nombre del documento actualizado correctamente.';
    }

    public function previousPadronPage(): void
    {
        $this->padronPage = max(1, $this->padronPage - 1);
    }

    public function nextPadronPage(): void
    {
        $this->padronPage = min($this->padronTotalPages(), $this->padronPage + 1);
    }

    public function updatedPadronSearch(): void
    {
        $this->padronPage = 1;
    }

    public function clearPadronSearch(): void
    {
        $this->padronSearch = '';
        $this->padronPage = 1;
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

    public function padronTotalPages(): int
    {
        return max(1, (int) ceil($this->padronTotalRows() / $this->padronPerPage));
    }

    public function padronTotalRows(): int
    {
        return (clone $this->filteredPadronQuery())->count();
    }

    public function padronRowsForPage(int $page)
    {
        return $this->filteredPadronQuery()
            ->forPage($page, $this->padronPerPage)
            ->get();
    }

    public function padronDocuments()
    {
        return UploadedDocument::query()
            ->latest('id')
            ->get();
    }

    public function documentPublicName(UploadedDocument $document): string
    {
        return $document->public_name ?: pathinfo($document->original_name, PATHINFO_FILENAME);
    }

    private function importPadronRows(string $filePath, UploadedDocument $uploadedDocument): int
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'documents' => 'No se pudo procesar el archivo Excel. Revisa que el archivo no esté dañado y que tenga encabezados en la primera fila con datos.',
            ]);
        }

        $importedRecords = 0;
        $foundHeaders = false;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, true);
            $headerRow = $this->findPadronHeaderRow($rows);

            if ($headerRow === null) {
                continue;
            }

            $foundHeaders = true;
            $headers = $this->buildHeaders($rows[$headerRow]);
            $map = $this->padronColumnMap($headers);

            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber <= $headerRow || ! $this->rowHasValues($row)) {
                    continue;
                }

                PadronRecord::create([
                    'uploaded_document_id' => $uploadedDocument->id,
                    'numero' => $this->cleanCellValue($row[$map['numero']] ?? ''),
                    'cedula' => $this->cleanCellValue($row[$map['cedula']] ?? ''),
                    'nombre' => $this->cleanCellValue($row[$map['nombre']] ?? ''),
                    'condicion' => $this->cleanCellValue($row[$map['condicion']] ?? ''),
                ]);

                $importedRecords++;
            }
        }

        $spreadsheet->disconnectWorksheets();

        if (! $foundHeaders) {
            throw ValidationException::withMessages([
                'documents' => 'No se encontró una fila de encabezados compatible. El archivo debe incluir: No., CÉDULA, NOMBRE y CONDICIÓN.',
            ]);
        }

        if ($importedRecords === 0) {
            throw ValidationException::withMessages([
                'documents' => 'El archivo tiene encabezados válidos, pero no contiene filas de padrón para importar.',
            ]);
        }

        return $importedRecords;
    }

    private function findPadronHeaderRow(array $rows): ?int
    {
        foreach ($rows as $rowNumber => $row) {
            if (! $this->rowHasValues($row)) {
                continue;
            }

            if ($this->hasPadronHeaders($this->buildHeaders($row))) {
                return (int) $rowNumber;
            }
        }

        return null;
    }

    private function buildHeaders(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $label = trim((string) $value);
            $headers[$column] = $label !== '' ? $label : "Columna {$column}";
        }

        return $headers;
    }

    private function hasPadronHeaders(array $headers): bool
    {
        $normalized = $this->normalizedHeaders($headers);

        return $this->findHeaderColumn($normalized, $this->cedulaAliases()) !== null
            && $this->findHeaderColumn($normalized, $this->nombreAliases()) !== null
            && $this->findHeaderColumn($normalized, $this->condicionAliases()) !== null;
    }

    private function padronColumnMap(array $headers): array
    {
        $normalized = $this->normalizedHeaders($headers);

        $map = [
            'numero' => $this->findHeaderColumn($normalized, $this->numeroAliases()) ?? array_key_first($headers),
            'cedula' => $this->findHeaderColumn($normalized, $this->cedulaAliases()),
            'nombre' => $this->findHeaderColumn($normalized, $this->nombreAliases()),
            'condicion' => $this->findHeaderColumn($normalized, $this->condicionAliases()),
        ];

        if (! $map['cedula'] || ! $map['nombre'] || ! $map['condicion']) {
            throw ValidationException::withMessages([
                'documents' => 'El archivo de padrón debe tener las columnas: No., CÉDULA, NOMBRE y CONDICIÓN.',
            ]);
        }

        return $map;
    }

    private function normalizedHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $column => $label) {
            $normalized[$column] = $this->normalizeHeader($label);
        }

        return $normalized;
    }

    private function findHeaderColumn(array $normalizedHeaders, array $aliases): ?string
    {
        foreach ($normalizedHeaders as $column => $header) {
            if (in_array($header, $aliases, true)) {
                return (string) $column;
            }
        }

        foreach ($normalizedHeaders as $column => $header) {
            foreach ($aliases as $alias) {
                if ($alias !== '' && str_contains($header, $alias)) {
                    return (string) $column;
                }
            }
        }

        return null;
    }

    private function numeroAliases(): array
    {
        return ['no', 'n', 'nro', 'num', 'numero', 'orden', 'item'];
    }

    private function cedulaAliases(): array
    {
        return ['cedula', 'ceduladeidentidad', 'nrocedula', 'numerocedula', 'numerodecedula', 'identificacion', 'documento', 'dni'];
    }

    private function nombreAliases(): array
    {
        return ['nombre', 'nombres', 'nombrecompleto', 'nombresapellidos', 'nombresyapellidos', 'apellidosnombres'];
    }

    private function condicionAliases(): array
    {
        return ['condicion', 'estado', 'estatus', 'situacion', 'calidad'];
    }

    private function rowHasValues(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanCellValue($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function cleanCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function publicDocumentName(string $originalName): string
    {
        $name = trim($this->publicName);

        return $name !== '' ? $name : pathinfo($originalName, PATHINFO_FILENAME);
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', '°'], ['a', 'e', 'i', 'o', 'u', 'u', 'n', ''], $value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value);

        return $value ?: '';
    }

    private function filteredPadronQuery()
    {
        $query = PadronRecord::query();
        $search = trim($this->padronSearch);

        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';

            $query->where(function ($query) use ($needle): void {
                $query
                    ->whereRaw('LOWER(numero) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(cedula) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(condicion) LIKE ?', [$needle]);
            });
        }

        return $query->orderBy('id');
    }

    private function deleteCurrentPadronRecords(): void
    {
        $documents = UploadedDocument::query()->get(['disk', 'path']);
        $pathsByDisk = [];

        foreach ($documents as $document) {
            $pathsByDisk[$document->disk][] = $document->path;
        }

        PadronRecord::query()->delete();
        UploadedDocument::query()->delete();

        foreach ($pathsByDisk as $disk => $paths) {
            Storage::disk($disk)->delete($paths);
        }
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
        <section class="page-toolbar" aria-labelledby="upload-title">
            <div>
                <p class="eyebrow">Registro electoral</p>
                <h1 id="upload-title" class="upload-title">Padrón Electoral</h1>
                <p class="upload-subtitle">Administra el padrón electoral</p>
            </div>

            <button class="primary-button toolbar-button" type="button" wire:click="openUploadPanel">
                Subir archivo
            </button>
        </section>

        @if ($showUploadPanel)
            <section class="upload-card" aria-label="Subir archivo de padrón">
                <div class="upload-main">
                    <div class="panel-title-row">
                        <div>
                            <p class="eyebrow">Carga de padrón</p>
                            <h2>Subir archivo</h2>
                        </div>
                        <button class="remove-button" type="button" wire:click="closeUploadPanel" aria-label="Cerrar panel de subida">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="m6 6 12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <p class="upload-subtitle">El archivo debe ser XLS o XLSX y contener las columnas: No., CÉDULA, NOMBRE y CONDICIÓN.</p>

                    <label class="name-field" for="padron-public-name">
                        <span>Nombre público del documento</span>
                        <input
                            id="padron-public-name"
                            type="text"
                            wire:model.live.debounce.250ms="publicName"
                            maxlength="150"
                            placeholder="Ejemplo: Padrón electoral oficial 2026"
                        >
                    </label>

                    <label class="dropzone compact-dropzone" data-dropzone for="documents">
                        <span>
                            <span class="dropzone-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 16V8"></path>
                                    <path d="m8 12 4-4 4 4"></path>
                                    <path d="M20 16.5a4.5 4.5 0 0 0-2.9-7.94 6 6 0 0 0-11.2 1.8A3.8 3.8 0 0 0 6 18h13"></path>
                                </svg>
                            </span>
                            <strong>Arrastra tu archivo de padrón aquí</strong>
                            <small>También puedes seleccionarlo desde tu equipo</small>
                            <span class="primary-button">Seleccionar XLS/XLSX</span>
                        </span>
                    </label>

                    <input
                        id="documents"
                        class="file-input"
                        type="file"
                        wire:model="documents"
                        data-file-input
                        multiple
                        accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    >

                    <div class="helper-grid" aria-label="Restricciones de carga">
                        <div class="helper-card">
                            <strong>Formatos permitidos</strong>
                            XLS, XLSX
                        </div>
                        <div class="helper-card">
                            <strong>Tamaño máximo</strong>
                            20 MB por archivo
                        </div>
                    </div>

                    <label class="replace-toggle">
                        <input type="checkbox" wire:model="replaceExistingPadron">
                        <span>
                            <strong>Actualizar padrón actual</strong>
                            Reemplaza los registros existentes antes de importar el nuevo archivo.
                        </span>
                    </label>

                    @if ($errors->any())
                        <div class="error-panel" role="alert">
                            Revisa el archivo seleccionado.
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <aside class="upload-side" aria-label="Archivos de padrón seleccionados">
                    <div class="files-header">
                        <div>
                            <h2>Archivos para importar</h2>
                        </div>
                        <span class="files-count">{{ count($queuedFiles) }} archivo{{ count($queuedFiles) === 1 ? '' : 's' }}</span>
                    </div>

                    <ul class="pending-list" data-pending-list wire:ignore></ul>

                    @if (count($queuedFiles) > 0)
                        <ul class="file-list">
                            @foreach ($queuedFiles as $index => $file)
                                <li class="file-row" wire:key="uploaded-file-{{ $index }}-{{ $file['name'] }}">
                                    <span class="file-icon">{{ $file['extension'] }}</span>
                                    <span class="file-meta">
                                        <span class="file-name">{{ $file['name'] }}</span>
                                        <span class="file-size">{{ $this->formatBytes($file['size']) }}</span>
                                        <span class="progress-track" aria-label="Carga completada">
                                            <span class="progress-bar" style="--progress: {{ $file['progress'] }}%"></span>
                                        </span>
                                    </span>
                                    <button class="remove-button" type="button" wire:click="removeFile({{ $index }})" aria-label="Eliminar {{ $file['name'] }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M18 6 6 18"></path>
                                            <path d="m6 6 12 12"></path>
                                        </svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="processing-panel" wire:loading wire:target="saveDocuments">
                            <span class="processing-label">Procesando e insertando registros del padrón en la base de datos...</span>
                            <span class="progress-track">
                                <span class="progress-bar progress-bar-striped" style="--progress: 100%"></span>
                            </span>
                        </div>
                    @else
                        <p class="empty-state">Los archivos seleccionados aparecerán aquí con su progreso.</p>
                    @endif

                    <button class="save-button" type="button" wire:click="saveDocuments" wire:loading.attr="disabled" @disabled(count($queuedFiles) === 0)>
                        {{ $replaceExistingPadron ? 'Actualizar padrón' : 'Importar padrón' }}
                    </button>
                    <p class="side-note">Cada fila se guarda en la tabla de padrón con las columnas solicitadas.</p>
                </aside>
            </section>
        @endif

        @if ($successMessage && ! $showUploadPanel)
            <div class="success-panel floating-message" role="status">{{ $successMessage }}</div>
        @endif

        @php
            $padronTotalRows = $this->padronTotalRows();
            $padronTotalPages = max(1, (int) ceil($padronTotalRows / $padronPerPage));
            $currentPadronPage = min($padronPage, $padronTotalPages);
            $padronRows = $this->padronRowsForPage($currentPadronPage);
            $padronDocuments = $this->padronDocuments();
        @endphp

        <section class="data-card document-admin-card" aria-labelledby="padron-documents-title">
            <div class="data-header">
                <div>
                    <p class="eyebrow">Documentos publicados</p>
                    <h2 id="padron-documents-title">Padrón electoral público</h2>
                </div>
                <span class="files-count">{{ $padronDocuments->count() }}</span>
            </div>

            @if ($padronDocuments->isEmpty())
                <p class="empty-state">Cuando importes un archivo de padrón, podrás editar aquí el nombre visible en la página pública.</p>
            @else
                <div class="document-admin-list">
                    @foreach ($padronDocuments as $document)
                        <div class="document-admin-row" wire:key="padron-document-name-{{ $document->id }}">
                            <div class="file-meta">
                                <span class="file-name">{{ $document->original_name }}</span>
                                <span class="file-size">{{ $this->formatBytes($document->size) }} · {{ $document->created_at->format('d/m/Y') }}</span>
                            </div>
                            <label class="name-field compact-name-field">
                                <span>Nombre público</span>
                                <input
                                    type="text"
                                    wire:model.defer="editingDocumentNames.{{ $document->id }}"
                                    value="{{ $this->documentPublicName($document) }}"
                                    maxlength="150"
                                >
                            </label>
                            <button class="secondary-button" type="button" wire:click="saveDocumentName({{ $document->id }})">
                                Guardar nombre
                            </button>
                            @error("editingDocumentNames.{$document->id}")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="data-card" aria-labelledby="padron-table-title">
            <div class="data-header">
                <div>
                    <p class="eyebrow">Registros importados</p>
                    <h2 id="padron-table-title">Tabla del padrón</h2>
                </div>
                <span class="files-count">{{ $padronTotalRows }} registro{{ $padronTotalRows === 1 ? '' : 's' }}</span>
            </div>

            <div class="table-tools">
                <label class="search-field" for="padron-search">
                    <span>Buscar en padrón</span>
                    <input
                        id="padron-search"
                        type="search"
                        wire:model.live.debounce.350ms="padronSearch"
                        placeholder="Buscar por  cédula, nombre o condición..."
                    >
                </label>

                @if ($padronSearch !== '')
                    <button class="secondary-button" type="button" wire:click="clearPadronSearch">Limpiar filtro</button>
                @endif
            </div>

            @if ($padronRows->isEmpty())
                <p class="empty-state">
                    {{ $padronSearch === '' ? 'Cuando subas un archivo XLS o XLSX, sus registros aparecerán en esta tabla.' : 'No hay registros que coincidan con el filtro actual.' }}
                </p>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>CÉDULA</th>
                                <th>NOMBRE</th>
                                <th>CONDICIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($padronRows as $row)
                                <tr wire:key="padron-row-{{ $row->id }}">
                                    <td>{{ (($currentPadronPage - 1) * $padronPerPage) + $loop->iteration }}</td>
                                    <td>{{ $row->cedula }}</td>
                                    <td>{{ $row->nombre }}</td>
                                    <td>{{ $row->condicion }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <button type="button" wire:click="previousPadronPage" @disabled($currentPadronPage <= 1)>Anterior</button>
                    <span>Página {{ $currentPadronPage }} de {{ $padronTotalPages }}</span>
                    <button type="button" wire:click="nextPadronPage" @disabled($currentPadronPage >= $padronTotalPages)>Siguiente</button>
                </div>
            @endif
        </section>
    </main>
</div>
