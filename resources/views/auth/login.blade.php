@extends('layouts.app')

@section('content')
    <main class="login-page">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-brand">
                <img class="login-logo" src="{{ asset('company-logo-transparent.png') }}" alt="Camino Unidos">
                <span class="login-badge">Acceso administrativo</span>
            </div>

            <div class="login-copy">
                <p class="eyebrow">Panel seguro</p>
                <h1 id="login-title">Iniciar sesión</h1>
                <p>Ingresa con tu usuario administrador para gestionar documentos del sistema.</p>
            </div>

            @if (session('status'))
                <div class="success-panel" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-panel" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form class="login-form" method="POST" action="{{ route('login.store') }}">
                @csrf

                <label class="field-group" for="email">
                    <span>Correo electrónico</span>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                        placeholder="admin@camino-unidos.local"
                    >
                </label>

                <label class="field-group" for="password">
                    <span>Contraseña</span>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        placeholder="Tu contraseña"
                    >
                </label>

                <button class="save-button" type="submit">Entrar al sistema</button>
            </form>

            <p class="login-help">
                Usuario por defecto: {{ config('auth.default_admin.email') }}
            </p>
        </section>
    </main>
@endsection
