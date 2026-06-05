<?php

use App\Models\MovementJoinRequest;
use Livewire\Component;

new class extends Component
{
    public string $requestSearch = '';

    public int $requestsPage = 1;

    public int $requestsPerPage = 10;

    public function updatedRequestSearch(): void
    {
        $this->requestsPage = 1;
    }

    public function clearRequestSearch(): void
    {
        $this->requestSearch = '';
        $this->requestsPage = 1;
    }

    public function previousRequestsPage(): void
    {
        $this->requestsPage = max(1, $this->requestsPage - 1);
    }

    public function nextRequestsPage(): void
    {
        $this->requestsPage = min($this->requestsTotalPages(), $this->requestsPage + 1);
    }

    public function requestsTotalRows(): int
    {
        return (clone $this->filteredRequestsQuery())->count();
    }

    public function requestsTotalPages(): int
    {
        return max(1, (int) ceil($this->requestsTotalRows() / $this->requestsPerPage));
    }

    public function requestsForPage(int $page)
    {
        return $this->filteredRequestsQuery()
            ->latest('id')
            ->forPage($page, $this->requestsPerPage)
            ->get();
    }

    private function filteredRequestsQuery()
    {
        $query = MovementJoinRequest::query();
        $search = trim($this->requestSearch);

        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';

            $query->where(function ($query) use ($needle): void {
                $query
                    ->whereRaw('LOWER(full_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(cedula) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(city_or_sector) LIKE ?', [$needle]);
            });
        }

        return $query;
    }
};
?>

<div class="upload-page">
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
        <section class="page-toolbar" aria-labelledby="join-requests-title">
            <div>
                <p class="eyebrow">Únete al movimiento</p>
                <h1 id="join-requests-title" class="upload-title">Solicitudes</h1>
                <p class="upload-subtitle">Consulta las personas que enviaron sus datos desde el formulario público.</p>
            </div>
        </section>

        @php
            $requestsTotalRows = $this->requestsTotalRows();
            $requestsTotalPages = $this->requestsTotalPages();
            $currentRequestsPage = min($requestsPage, $requestsTotalPages);
            $requests = $this->requestsForPage($currentRequestsPage);
        @endphp

        <section class="data-card" aria-labelledby="requests-table-title">
            <div class="data-header">
                <div>
                    <p class="eyebrow">Registros recibidos</p>
                    <h2 id="requests-table-title">Solicitudes de unión</h2>
                </div>
                <span class="files-count">{{ $requestsTotalRows }} solicitud{{ $requestsTotalRows === 1 ? '' : 'es' }}</span>
            </div>

            <div class="table-tools">
                <label class="search-field" for="join-request-search">
                    <span>Buscar solicitud</span>
                    <input
                        id="join-request-search"
                        type="search"
                        wire:model.live.debounce.350ms="requestSearch"
                        placeholder="Buscar por nombre, cédula, teléfono, correo o sector..."
                    >
                </label>

                @if ($requestSearch !== '')
                    <button class="secondary-button" type="button" wire:click="clearRequestSearch">Limpiar filtro</button>
                @endif
            </div>

            @if ($requests->isEmpty())
                <p class="empty-state">
                    {{ $requestSearch === '' ? 'Todavía no hay solicitudes registradas.' : 'No hay solicitudes que coincidan con el filtro actual.' }}
                </p>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Sector</th>
                                <th>Mensaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $request)
                                <tr wire:key="join-request-{{ $request->id }}">
                                    <td>{{ $request->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $request->full_name }}</td>
                                    <td>{{ $request->cedula }}</td>
                                    <td>{{ $request->phone }}</td>
                                    <td>{{ $request->email ?: 'Sin correo' }}</td>
                                    <td>{{ $request->city_or_sector ?: 'Sin sector' }}</td>
                                    <td>{{ $request->message ?: 'Sin mensaje' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <button type="button" wire:click="previousRequestsPage" @disabled($currentRequestsPage <= 1)>Anterior</button>
                    <span>Página {{ $currentRequestsPage }} de {{ $requestsTotalPages }}</span>
                    <button type="button" wire:click="nextRequestsPage" @disabled($currentRequestsPage >= $requestsTotalPages)>Siguiente</button>
                </div>
            @endif
        </section>
    </main>
</div>
