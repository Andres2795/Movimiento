<?php

namespace App\Http\Controllers;

use App\Models\MovementJoinRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MovementJoinRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'cedula' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'city_or_sector' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:800'],
            'accept_terms' => ['accepted'],
        ], [
            'full_name.required' => 'Ingresa tu nombre completo.',
            'cedula.required' => 'Ingresa tu número de cédula.',
            'phone.required' => 'Ingresa tu teléfono de contacto.',
            'email.email' => 'Ingresa un correo válido.',
            'accept_terms.accepted' => 'Confirma que aceptas ser contactado por el movimiento.',
        ]);

        unset($validated['accept_terms']);

        MovementJoinRequest::create($validated);

        return redirect()
            ->to(route('client.home').'#contacto')
            ->with('join_success', 'Tus datos fueron registrados correctamente. Pronto nos comunicaremos contigo.');
    }
}
