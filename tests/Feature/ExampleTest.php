<?php

namespace Tests\Feature;

use App\Models\OrganicStructureDocument;
use App\Models\PadronRecord;
use App\Models\UploadedDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_administrator_can_access_document_upload(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Padrón');
        $response->assertSee('Subir archivo');
        $response->assertDontSee('Seleccionar XLS/XLSX');
    }

    public function test_administrator_can_login_with_valid_credentials(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Admin123!',
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'Admin123!',
        ]);

        $response->assertRedirect(route('documents.upload'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_padron_upload_imports_rows_into_database(): void
    {
        Storage::fake('public');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Padron');
        $sheet->fromArray([
            ['PADRÓN GENERAL DEL MOVIMIENTO'],
            [],
            ['Nro.', 'Identificación', 'Nombre completo', 'Estado'],
            ['1', '0102030405', 'Ana Torres', 'Activo'],
            ['2', '0607080910', 'Luis Perez', 'Pendiente'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'excel-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $file = File::createWithContent('padron.xlsx', file_get_contents($path))
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Livewire::test('upload-documents')
            ->assertSee('Subir archivo')
            ->assertDontSee('Seleccionar XLS/XLSX')
            ->call('openUploadPanel')
            ->assertSee('Seleccionar XLS/XLSX')
            ->upload('documents', [$file], true)
            ->call('saveDocuments')
            ->assertSee('Se importaron 2 registros del padrón.');

        $this->assertDatabaseHas('uploaded_documents', [
            'original_name' => 'padron.xlsx',
            'extension' => 'xlsx',
        ]);

        $this->assertDatabaseHas('padron_records', [
            'numero' => '1',
            'cedula' => '0102030405',
            'nombre' => 'Ana Torres',
            'condicion' => 'Activo',
        ]);

        $this->assertSame(1, UploadedDocument::count());
        $this->assertSame(2, PadronRecord::count());
        $this->assertSame('Ana Torres', PadronRecord::first()->nombre);
    }

    public function test_padron_table_renders_sequential_numbers(): void
    {
        Storage::fake('public');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['No.', 'CÉDULA', 'NOMBRE', 'CONDICIÓN'],
            ['25', '0102030405', 'Ana Torres', 'Activo'],
            ['48', '0607080910', 'Luis Perez', 'Pendiente'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'excel-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $file = File::createWithContent('padron-secuencial.xlsx', file_get_contents($path))
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Livewire::test('upload-documents')
            ->upload('documents', [$file], true)
            ->call('saveDocuments')
            ->assertSeeHtml('<td>1</td>')
            ->assertSeeHtml('<td>2</td>')
            ->assertSeeInOrder(['Ana Torres', 'Luis Perez']);
    }

    public function test_padron_update_replaces_previous_records(): void
    {
        Storage::fake('public');

        PadronRecord::create([
            'numero' => '99',
            'cedula' => '9999999999',
            'nombre' => 'Registro viejo',
            'condicion' => 'Inactivo',
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['No.', 'CÉDULA', 'NOMBRE', 'CONDICIÓN'],
            ['1', '0102030405', 'Ana Torres', 'Activo'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'excel-replace-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $file = File::createWithContent('padron-actualizado.xlsx', file_get_contents($path))
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Livewire::test('upload-documents')
            ->set('replaceExistingPadron', true)
            ->upload('documents', [$file], true)
            ->call('saveDocuments')
            ->assertSee('Padrón actualizado correctamente.');

        $this->assertSame(1, PadronRecord::count());
        $this->assertSame('Ana Torres', PadronRecord::first()->nombre);
        $this->assertDatabaseMissing('padron_records', [
            'nombre' => 'Registro viejo',
        ]);
    }

    public function test_administrator_can_access_organic_structure_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/estructura-organica');

        $response->assertStatus(200);
        $response->assertSee('Estructura orgánica');
        $response->assertSee('Subir archivo');
        $response->assertDontSee('Seleccionar PDFs');
    }

    public function test_organic_structure_upload_panel_is_opened_on_demand(): void
    {
        Livewire::test('organic-structure')
            ->assertSee('Subir archivo')
            ->assertDontSee('Seleccionar PDFs')
            ->call('openUploadPanel')
            ->assertSee('Seleccionar PDFs')
            ->call('closeUploadPanel')
            ->assertDontSee('Seleccionar PDFs');
    }

    public function test_organic_structure_upload_stores_multiple_pdf_documents(): void
    {
        Storage::fake('public');

        $pdfA = File::createWithContent('organigrama-central.pdf', '%PDF-1.4 estructura a')
            ->mimeType('application/pdf');
        $pdfB = File::createWithContent('organigrama-territorial.pdf', '%PDF-1.4 estructura b')
            ->mimeType('application/pdf');

        Livewire::test('organic-structure')
            ->upload('documents', [$pdfA, $pdfB], true)
            ->call('saveDocuments')
            ->assertSee('2 PDFs guardados correctamente.');

        $this->assertSame(2, OrganicStructureDocument::count());
        $this->assertDatabaseHas('organic_structure_documents', [
            'title' => 'organigrama-central',
            'original_name' => 'organigrama-central.pdf',
        ]);
        $this->assertDatabaseHas('organic_structure_documents', [
            'title' => 'organigrama-territorial',
            'original_name' => 'organigrama-territorial.pdf',
        ]);
    }

    public function test_organic_structure_pdf_can_be_deleted_from_library(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('organic-structure/delete-me.pdf', 'pdf-content');

        $document = OrganicStructureDocument::create([
            'title' => 'delete-me',
            'original_name' => 'delete-me.pdf',
            'stored_name' => 'delete-me.pdf',
            'path' => 'organic-structure/delete-me.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'size' => 12,
        ]);

        Livewire::test('organic-structure')
            ->call('deleteDocument', $document->id)
            ->assertSee('PDF eliminado correctamente.');

        $this->assertDatabaseMissing('organic_structure_documents', [
            'id' => $document->id,
        ]);
        Storage::disk('public')->assertMissing('organic-structure/delete-me.pdf');
    }

    public function test_organic_structure_pdf_form_upload_stores_documents(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $pdf = File::createWithContent('regimen-organico.pdf', '%PDF-1.4 documento con imagen')
            ->mimeType('application/pdf');

        $response = $this
            ->actingAs($admin)
            ->post(route('organic-structure.documents.store'), [
                'documents' => [$pdf],
            ]);

        $response
            ->assertRedirect(route('organic-structure'))
            ->assertSessionHas('organic_success', 'PDF guardado correctamente.');

        $this->assertSame(1, OrganicStructureDocument::count());
        $this->assertDatabaseHas('organic_structure_documents', [
            'title' => 'regimen-organico',
            'original_name' => 'regimen-organico.pdf',
        ]);
    }
}
