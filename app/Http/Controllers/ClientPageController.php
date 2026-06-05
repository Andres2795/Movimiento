<?php

namespace App\Http\Controllers;

use App\Models\GalleryEvent;
use App\Models\OrganicStructureDocument;
use App\Models\PublicPageSetting;
use Illuminate\Contracts\View\View;

class ClientPageController extends Controller
{
    public function index(): View
    {
        $publicPageSetting = PublicPageSetting::current();

        $organicDocuments = OrganicStructureDocument::query()
            ->latest('id')
            ->get()
            ->map(fn (OrganicStructureDocument $document): array => [
                'name' => $document->title,
                'url' => asset('storage/'.$document->path),
                'size' => $document->size,
                'date' => $document->created_at,
                'type' => 'Estructura orgánica',
                'button' => 'Abrir PDF',
            ]);

        $galleryEvents = GalleryEvent::query()
            ->with(['photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->whereHas('photos')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        return view('client.home', [
            'heroImageUrl' => $publicPageSetting->hero_image_path
                ? asset('storage/'.$publicPageSetting->hero_image_path)
                : null,
            'documents' => $organicDocuments
                ->sortByDesc('date')
                ->values(),
            'galleryEvents' => $galleryEvents,
        ]);
    }
}
