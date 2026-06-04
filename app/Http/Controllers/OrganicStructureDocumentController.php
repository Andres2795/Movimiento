<?php

namespace App\Http\Controllers;

use App\Models\OrganicStructureDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OrganicStructureDocumentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->ensureUploadedFilesAreValid($request);

        $validated = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf', 'max:20480'],
        ], [
            'documents.required' => 'Selecciona al menos un PDF de estructura orgánica.',
            'documents.*.uploaded' => 'El PDF no pudo subirse al servidor. Verifica que pese 20 MB o menos y vuelve a seleccionarlo.',
            'documents.*.mimes' => 'Solo se permiten documentos PDF.',
            'documents.*.max' => 'Cada PDF debe pesar 20 MB o menos.',
            'documents.*.file' => 'Uno de los elementos seleccionados no es un archivo válido.',
        ]);

        $lastDocumentId = null;

        foreach ($validated['documents'] as $document) {
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

            $lastDocumentId = $record->id;
        }

        $count = count($validated['documents']);
        $message = $count === 1
            ? 'PDF guardado correctamente.'
            : "{$count} PDFs guardados correctamente.";

        return redirect()
            ->route('organic-structure')
            ->with('organic_success', $message)
            ->with('selected_document_id', $lastDocumentId);
    }

    private function ensureUploadedFilesAreValid(Request $request): void
    {
        $files = $request->file('documents', []);

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            Log::warning('Organic structure PDF upload failed before validation.', [
                'original_name' => $file->getClientOriginalName(),
                'client_mime' => $file->getClientMimeType(),
                'error_code' => $file->getError(),
                'error_message' => $file->getErrorMessage(),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir'),
            ]);

            throw ValidationException::withMessages([
                'documents' => $this->uploadErrorMessage($file),
            ]);
        }
    }

    private function uploadErrorMessage(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El PDF supera el limite permitido por PHP. Reinicia el servidor con upload_max_filesize=20M y post_max_size=25M.',
            UPLOAD_ERR_PARTIAL => 'El PDF se subio de forma incompleta. Vuelve a seleccionarlo e intenta guardarlo otra vez.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP no tiene carpeta temporal para recibir el PDF.',
            UPLOAD_ERR_CANT_WRITE => 'PHP no pudo escribir el PDF temporal en el disco.',
            UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la subida del PDF.',
            default => 'El PDF no pudo subirse al servidor. Vuelve a seleccionarlo e intenta guardarlo otra vez.',
        };
    }
}
