<?php

namespace Tests\Feature;

use App\Models\OrganicStructureDocument;
use App\Models\GalleryEvent;
use App\Models\GalleryPhoto;
use App\Models\MovementJoinRequest;
use App\Models\PadronRecord;
use App\Models\PublicPageSetting;
use App\Models\UploadedDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_page_is_visible_without_authentication(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Movimiento Caminemos Unidos');
        $response->assertSee('Galería');
        $response->assertSee('Acceder');
        $response->assertSee('Enviar solicitud');
    }

    public function test_public_home_page_shows_configured_hero_image(): void
    {
        PublicPageSetting::create([
            'hero_image_path' => 'public-page/hero.jpg',
            'hero_image_original_name' => 'hero.jpg',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('storage/public-page/hero.jpg');
        $response->assertSee('Imagen principal del Movimiento Caminemos Unidos');
    }

    public function test_guest_is_redirected_to_login_from_admin_panel(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    public function test_public_client_page_is_visible_without_authentication(): void
    {
        $response = $this->get('/cliente');

        $response->assertRedirect('/');
    }

    public function test_public_client_page_lists_transparency_documents(): void
    {
        OrganicStructureDocument::create([
            'title' => 'Régimen orgánico público',
            'original_name' => 'REGIMEN ORGANICO CAMINEMOS.pdf',
            'stored_name' => 'regimen-organico.pdf',
            'path' => 'organic-structure/regimen-organico.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'size' => 3145728,
        ]);
        UploadedDocument::create([
            'original_name' => 'padron-electoral.xlsx',
            'public_name' => 'Padrón electoral oficial',
            'stored_name' => 'padron-electoral.xlsx',
            'path' => 'padron/padron-electoral.xlsx',
            'disk' => 'public',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'extension' => 'xlsx',
            'size' => 1048576,
        ]);

        $response = $this->get(route('client.home'));

        $response->assertStatus(200);
        $response->assertSee('Régimen orgánico público');
        $response->assertSee('Estructura orgánica');
        $response->assertDontSee('Padrón electoral oficial');
        $response->assertDontSee('Padrón electoral');
        $response->assertSee('Abrir PDF');
        $response->assertSee('3.0 MB');
    }

    public function test_public_home_page_lists_gallery_events(): void
    {
        Storage::fake('public');

        $event = GalleryEvent::create([
            'title' => 'Recorrido territorial',
            'description' => 'Actividad ciudadana en el centro.',
            'event_date' => '2026-06-01',
        ]);

        $photo = GalleryPhoto::create([
            'gallery_event_id' => $event->id,
            'original_name' => 'recorrido-1.jpg',
            'stored_name' => 'recorrido-1.jpg',
            'path' => 'gallery/events/'.$event->id.'/recorrido-1.jpg',
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'sort_order' => 1,
        ]);

        Storage::disk('public')->put($photo->path, 'image-content');

        $response = $this->get(route('client.home'));

        $response->assertStatus(200);
        $response->assertSee('Recorrido territorial');
        $response->assertSee('Actividad ciudadana en el centro.');
        $response->assertSee('storage/gallery/events/'.$event->id.'/recorrido-1.jpg');
    }

    public function test_public_home_page_paginates_gallery_events(): void
    {
        Storage::fake('public');

        foreach ([
            ['title' => 'Evento A', 'date' => '2026-06-01', 'photo' => 'a.jpg'],
            ['title' => 'Evento B', 'date' => '2026-06-02', 'photo' => 'b.jpg'],
            ['title' => 'Evento C', 'date' => '2026-06-03', 'photo' => 'c.jpg'],
        ] as $data) {
            $event = GalleryEvent::create([
                'title' => $data['title'],
                'description' => null,
                'event_date' => $data['date'],
            ]);

            $photo = GalleryPhoto::create([
                'gallery_event_id' => $event->id,
                'original_name' => $data['photo'],
                'stored_name' => $data['photo'],
                'path' => 'gallery/events/'.$event->id.'/'.$data['photo'],
                'disk' => 'public',
                'mime_type' => 'image/jpeg',
                'size' => 2048,
                'sort_order' => 1,
            ]);

            Storage::disk('public')->put($photo->path, 'image-content');
        }

        $response = $this->get(route('client.home'));
        $response->assertStatus(200);
        $response->assertSee('Evento C');
        $response->assertSee('Evento B');
        $response->assertDontSee('Evento A');

        $responsePage2 = $this->get(route('client.home', ['galeria' => 2]));
        $responsePage2->assertStatus(200);
        $responsePage2->assertSee('Evento A');
        $responsePage2->assertDontSee('Evento C');
    }

    public function test_public_join_form_stores_movement_request(): void
    {
        $response = $this->post(route('movement.join.store'), [
            'full_name' => 'Ana Torres',
            'cedula' => '0102030405',
            'phone' => '0999999999',
            'email' => 'ana@example.com',
            'city_or_sector' => 'Centro',
            'message' => 'Quiero participar en el movimiento.',
            'accept_terms' => '1',
        ]);

        $response->assertRedirect(route('client.home').'#contacto');
        $this->assertSame(1, MovementJoinRequest::count());
        $this->assertDatabaseHas('movement_join_requests', [
            'full_name' => 'Ana Torres',
            'cedula' => '0102030405',
            'phone' => '0999999999',
        ]);
    }

    public function test_administrator_can_access_document_upload(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin');

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
            ->set('publicName', 'Padrón electoral general')
            ->upload('documents', [$file], true)
            ->call('saveDocuments')
            ->assertSee('Se importaron 2 registros del padrón.');

        $this->assertDatabaseHas('uploaded_documents', [
            'original_name' => 'padron.xlsx',
            'public_name' => 'Padrón electoral general',
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

    public function test_administrator_can_edit_public_padron_document_name(): void
    {
        $document = UploadedDocument::create([
            'original_name' => 'padron-electoral.xlsx',
            'public_name' => 'Nombre anterior',
            'stored_name' => 'padron-electoral.xlsx',
            'path' => 'padron/padron-electoral.xlsx',
            'disk' => 'public',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'extension' => 'xlsx',
            'size' => 1024,
        ]);

        Livewire::test('upload-documents')
            ->set("editingDocumentNames.{$document->id}", 'Padrón electoral actualizado')
            ->call('saveDocumentName', $document->id)
            ->assertSee('Nombre del documento actualizado correctamente.');

        $this->assertDatabaseHas('uploaded_documents', [
            'id' => $document->id,
            'public_name' => 'Padrón electoral actualizado',
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
        $response->assertSee('Documento');
        $response->assertSee('Subir archivo');
        $response->assertDontSee('Seleccionar PDFs');
    }

    public function test_administrator_can_access_public_page_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/pagina-publica');

        $response->assertStatus(200);
        $response->assertSee('Banner');
        $response->assertSee('Guardar imagen');
    }

    public function test_administrator_can_access_gallery_manager(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/galeria');

        $response->assertStatus(200);
        $response->assertSee('Galería de fotos');
        $response->assertSee('Subir fotografías');
    }

    public function test_administrator_can_access_join_requests_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        MovementJoinRequest::create([
            'full_name' => 'Ana Torres',
            'cedula' => '0102030405',
            'phone' => '0999999999',
            'email' => 'ana@example.com',
            'city_or_sector' => 'Centro',
            'message' => 'Quiero participar.',
        ]);

        $response = $this->actingAs($admin)->get('/solicitudes');

        $response->assertStatus(200);
        $response->assertSee('Solicitudes');
        $response->assertSee('Ana Torres');
        $response->assertSee('0102030405');
    }

    public function test_guest_is_redirected_from_join_requests_page(): void
    {
        $response = $this->get('/solicitudes');

        $response->assertRedirect('/login');
    }

    public function test_administrator_can_update_public_page_hero_image(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('hero.jpg', 1200, 900)->size(512);

        Livewire::test('public-page-settings')
            ->upload('heroImage', [$image])
            ->call('saveHeroImage')
            ->assertSee('Imagen principal actualizada correctamente.');

        $setting = PublicPageSetting::first();

        $this->assertNotNull($setting);
        $this->assertSame('hero.jpg', $setting->hero_image_original_name);
        Storage::disk('public')->assertExists($setting->hero_image_path);
    }

    public function test_administrator_can_store_gallery_event_with_photos(): void
    {
        Storage::fake('public');

        $photoA = UploadedFile::fake()->image('evento-1.jpg', 1200, 900)->size(1024);
        $photoB = UploadedFile::fake()->image('evento-2.png', 1000, 800)->size(512);

        Livewire::test('gallery-manager')
            ->set('eventTitle', 'Recorrido territorial')
            ->set('eventDate', '2026-06-01')
            ->set('eventDescription', 'Actividad ciudadana en el centro.')
            ->upload('photos', [$photoA, $photoB], true)
            ->call('saveEvent')
            ->assertSee('se guardó correctamente');

        $this->assertSame(1, GalleryEvent::count());
        $this->assertSame(2, GalleryPhoto::count());
        $this->assertDatabaseHas('gallery_events', [
            'title' => 'Recorrido territorial',
        ]);

        $event = GalleryEvent::firstOrFail();
        $this->assertDatabaseHas('gallery_photos', [
            'gallery_event_id' => $event->id,
            'original_name' => 'evento-1.jpg',
        ]);

        $photo = GalleryPhoto::firstOrFail();
        Storage::disk('public')->assertExists($photo->path);
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
                'document_name' => 'Régimen orgánico oficial',
            ]);

        $response
            ->assertRedirect(route('organic-structure'))
            ->assertSessionHas('organic_success', 'PDF guardado correctamente.');

        $this->assertSame(1, OrganicStructureDocument::count());
        $this->assertDatabaseHas('organic_structure_documents', [
            'title' => 'Régimen orgánico oficial',
            'original_name' => 'regimen-organico.pdf',
        ]);
    }

    public function test_administrator_can_edit_public_organic_document_name_from_library(): void
    {
        Storage::fake('public');

        $document = OrganicStructureDocument::create([
            'title' => 'Nombre anterior',
            'original_name' => 'regimen-organico.pdf',
            'stored_name' => 'regimen-organico.pdf',
            'path' => 'organic-structure/regimen-organico.pdf',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        Livewire::test('organic-structure')
            ->assertSee('Cambiar nombre')
            ->call('editDocumentName', $document->id)
            ->set("editingDocumentNames.{$document->id}", 'Régimen orgánico actualizado')
            ->call('saveDocumentName', $document->id)
            ->assertSee('Nombre del PDF actualizado correctamente.');

        $this->assertDatabaseHas('organic_structure_documents', [
            'id' => $document->id,
            'title' => 'Régimen orgánico actualizado',
        ]);
    }

}
