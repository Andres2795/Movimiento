<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Movimiento Caminemos | Portal ciudadano</title>
    <link rel="icon" type="image/png" href="{{ asset('company-logo-transparent.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('company-logo-transparent.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/client-page.css') }}?v={{ filemtime(public_path('assets/css/client-page.css')) }}">
</head>
<body>
    <div class="client-page">
        <header class="site-header">
            <div class="top-strip">
                <span>info@caminemos.ec</span>
                <span>Movimiento ciudadano con transparencia</span>
            </div>

            <nav class="public-nav" aria-label="Navegación pública">
                <a class="brand-mark" href="#inicio" aria-label="Ir al inicio">
                    <img src="{{ asset('company-logo-transparent.png') }}" alt="Movimiento Caminemos">
                </a>

                <div class="nav-links">
                    <a href="#inicio">Inicio</a>
                    <a href="#propuestas">Nuestro enfoque</a>
                    <a href="#galeria">Galería</a>
                    <a href="#transparencia">Transparencia</a>
                    <a href="#contacto">Contactos</a>
                </div>

                @if (auth()->check() && auth()->user()?->role === 'administrador' && auth()->user()?->is_active)
                    <a class="admin-link" href="{{ route('documents.upload') }}">Administrador</a>
                @else
                    <a class="admin-link" href="{{ route('login') }}">
                        <svg class="admin-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M7 11V8a5 5 0 0 1 10 0v3"></path>
                            <rect x="5" y="11" width="14" height="10" rx="2"></rect>
                            <path d="M12 15v2"></path>
                        </svg>
                        Acceder
                    </a>
                @endif
            </nav>
        </header>

        <main>
            <section id="inicio" class="hero-section">
                <div class="hero-copy">
                    <p class="section-kicker">Movimiento Caminemos Unidos</p>
                    <h1>Caminamos juntos por una ciudad más justa, abierta y participativa.</h1>
                    <p>
                        Un espacio ciudadano para organizar propuestas, fortalecer la participación y publicar información institucional de forma clara.
                    </p>
                    <div class="hero-actions">
                        <a class="primary-cta" href="#transparencia">Ver transparencia</a>
                        <a class="secondary-cta" href="#propuestas">Nuestro enfoque</a>
                    </div>
                </div>

                @if ($heroImageUrl)
                    <figure class="hero-image-card">
                        <img src="{{ $heroImageUrl }}" alt="Imagen principal del Movimiento Caminemos Unidos">
                    </figure>
                @else
                    <aside class="hero-panel" aria-label="Resumen del movimiento">
                        <span class="movement-number">125</span>
                        <strong>Unidad, participación y control ciudadano.</strong>
                        <p>La información pública debe estar a la vista, organizada y disponible para todos.</p>
                    </aside>
                @endif
            </section>

            <section id="propuestas" class="program-section" aria-labelledby="program-title">
                <div class="section-heading">
                    <p class="section-kicker">Nuestro enfoque</p>
                    <h2 id="program-title">Un movimiento con compromisos visibles</h2>
                </div>

                <div class="program-grid">
                    <article>
                        <span class="program-icon">01</span>
                        <h3>Participación ciudadana</h3>
                        <p>Impulsamos espacios de diálogo para que la ciudadanía participe en decisiones locales y comunitarias.</p>
                    </article>

                    <article>
                        <span class="program-icon">02</span>
                        <h3>Transparencia pública</h3>
                        <p>Los documentos institucionales se publican para consulta directa, sin barreras ni trámites innecesarios.</p>
                    </article>

                    <article>
                        <span class="program-icon">03</span>
                        <h3>Organización territorial</h3>
                        <p>Fortalecemos equipos barriales, comunitarios y ciudadanos para sostener una estructura cercana.</p>
                    </article>
                </div>
            </section>

            <section id="galeria" class="gallery-section" aria-labelledby="gallery-title">
                <div class="section-heading">
                    <p class="section-kicker">Galería</p>
                    <h2 id="gallery-title">Galería fotográfica del movimiento</h2>
                    <p>Fotos organizadas por evento para revisar recorridos, reuniones y actividades públicas del movimiento.</p>
                </div>

                @if ($galleryEvents->isEmpty())
                    <div class="empty-public-state">
                        Todavía no hay fotografías publicadas en la galería.
                    </div>
                @else
                    <div class="gallery-stack">
                        @foreach ($galleryEvents as $event)
                            @php
                                $coverPhoto = $event->photos->first();
                                $secondaryPhotos = $event->photos->skip(1);
                            @endphp

                            <article class="gallery-event-card">
                                <div class="gallery-event-header">
                                    <div>
                                        <span class="document-badge">Evento</span>
                                        <h3>{{ $event->title }}</h3>
                                        <p>
                                            {{ ($event->event_date ?? $event->created_at)->format('d/m/Y') }}
                                            · {{ $event->photos->count() }} foto{{ $event->photos->count() === 1 ? '' : 's' }}
                                        </p>
                                    </div>
                                    <div class="gallery-count-pill">{{ $event->photos->count() }}</div>
                                </div>

                                <div class="gallery-event-body">
                                    <a class="gallery-cover" href="{{ asset('storage/'.$coverPhoto->path) }}" target="_blank" rel="noopener">
                                        <img src="{{ asset('storage/'.$coverPhoto->path) }}" alt="{{ $coverPhoto->original_name }}">
                                    </a>

                                    <div class="gallery-side-panel">
                                        @if ($event->description)
                                            <p class="gallery-event-description">{{ $event->description }}</p>
                                        @endif

                                        @if ($secondaryPhotos->isNotEmpty())
                                            <div class="gallery-thumb-grid">
                                                @foreach ($secondaryPhotos->take(4) as $photo)
                                                    <a class="gallery-thumb" href="{{ asset('storage/'.$photo->path) }}" target="_blank" rel="noopener">
                                                        <img src="{{ asset('storage/'.$photo->path) }}" alt="{{ $photo->original_name }}">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="gallery-empty-note">
                                                Este evento contiene una fotografía principal publicada por el equipo del movimiento.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($galleryEvents->hasPages())
                    <div class="gallery-pagination">
                        {{ $galleryEvents->links() }}
                    </div>
                @endif
            </section>

            <section id="transparencia" class="transparency-section" aria-labelledby="transparency-title">
                <div class="section-heading">
                    <p class="section-kicker">Transparencia</p>
                    <h2 id="transparency-title">Documentos del movimiento</h2>
                    <p>Consulta los documentos subidos desde el panel administrativo: estructura orgánica y padrón electoral.</p>
                </div>

                @if ($documents->isEmpty())
                    <div class="empty-public-state">
                        Todavía no hay documentos publicados en transparencia.
                    </div>
                @else
                    <div class="document-grid">
                        @foreach ($documents as $document)
                            <article class="document-card">
                                <div class="document-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"></path>
                                        <path d="M14 2v5h5"></path>
                                        <path d="M9 13h6"></path>
                                        <path d="M9 17h6"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3>{{ $document['name'] }}</h3>
                            
                                </div>
                                <a href="{{ $document['url'] }}" target="_blank" rel="noopener">
                                    {{ $document['button'] }}
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
            <section id="contacto" class="contact-section" aria-labelledby="contact-title">
                <div class="contact-copy">
                    <p class="section-kicker">Contacto</p>
                    <h2 id="contact-title">Únete al movimiento</h2>
                    <p>Registra tus datos para que el equipo territorial pueda contactarte y sumarte a las actividades ciudadanas.</p>
                </div>

                <form class="join-form" action="{{ route('movement.join.store') }}#contacto" method="POST">
                    @csrf

                    @if (session('join_success'))
                        <div class="join-success" role="status">{{ session('join_success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="join-error" role="alert">
                            Revisa los datos ingresados.
                        </div>
                    @endif

                    <div class="form-grid">
                        <label>
                            <span>Nombre completo</span>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" required maxlength="120">
                            @error('full_name') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Cédula</span>
                            <input type="text" name="cedula" value="{{ old('cedula') }}" required maxlength="20">
                            @error('cedula') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Teléfono</span>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="30">
                            @error('phone') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Correo</span>
                            <input type="email" name="email" value="{{ old('email') }}" maxlength="150">
                            @error('email') <small>{{ $message }}</small> @enderror
                        </label>

                        <label class="full-field">
                            <span>Ciudad o sector</span>
                            <input type="text" name="city_or_sector" value="{{ old('city_or_sector') }}" maxlength="120">
                            @error('city_or_sector') <small>{{ $message }}</small> @enderror
                        </label>

                        <label class="full-field">
                            <span>Mensaje</span>
                            <textarea name="message" rows="4" maxlength="800">{{ old('message') }}</textarea>
                            @error('message') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>

                    <label class="consent-field">
                        <input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms')) required>
                        <span>Acepto que el movimiento me contacte para actividades e información institucional.</span>
                    </label>
                    @error('accept_terms') <small class="consent-error">{{ $message }}</small> @enderror

                    <button class="primary-cta join-submit" type="submit">Enviar solicitud</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
