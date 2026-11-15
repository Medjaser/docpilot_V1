<?php

use App\Domain\Documents\PortalFileProcessor;
use App\Domain\Documents\SimplePdfGenerator;
use App\Enums\DocumentStatus;
use App\Enums\RequestStatus;
use App\Livewire\Portal\Upload as PortalUpload;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Subcontractor;
use App\Models\User;
use App\Support\UploadLimitsChecker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Construit une image JPEG valide contenant un segment APP1 EXIF avec Orientation=$orientation
 * et un tag ASCII factice (ex. GPS / modèle d'appareil) pour tester la rotation et le nettoyage EXIF.
 */
function makeJpegWithExifOrientation(int $width, int $height, int $orientation = 6): string
{
    $img = imagecreatetruecolor($width, $height);
    $red = imagecolorallocate($img, 220, 30, 30);
    $blue = imagecolorallocate($img, 30, 60, 220);
    imagefill($img, 0, 0, $red);
    imagefilledrectangle($img, 0, 0, (int) max(1, $width / 4), $height, $blue);

    ob_start();
    imagejpeg($img, null, 92);
    $jpeg = (string) ob_get_clean();

    // Construction d'un en-tête TIFF Little-Endian ("II") avec 2 entrées IFD0 :
    // 1) Tag 0x0112 (Orientation, SHORT=3, count=1, value=$orientation)
    // 2) Tag 0x010F (Make, ASCII=2, "iPhone GPS Test\0")
    $makeStr = "iPhone GPS Test\x00";
    $ifdOffset = 8;
    $numEntries = 2;
    $dataOffset = $ifdOffset + 2 + ($numEntries * 12) + 4;

    $tiff = 'II'
        .pack('v', 42)
        .pack('V', $ifdOffset)
        .pack('v', $numEntries)
        // Entry 1: Orientation (0x0112)
        .pack('v', 0x0112)
        .pack('v', 3)
        .pack('V', 1)
        .pack('v', $orientation)
        .pack('v', 0)
        // Entry 2: Make (0x010F)
        .pack('v', 0x010F)
        .pack('v', 2)
        .pack('V', strlen($makeStr))
        .pack('V', $dataOffset)
        // Next IFD offset = 0
        .pack('V', 0)
        .$makeStr;

    $app1Payload = "Exif\x00\x00".$tiff;
    $app1Segment = "\xFF\xE1".pack('n', strlen($app1Payload) + 2).$app1Payload;

    // Insertion juste après SOI (\xFF\xD8)
    return substr($jpeg, 0, 2).$app1Segment.substr($jpeg, 2);
}

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    config(['docpilot.uploads.disk' => 'local']);

    $this->company = Company::factory()->create([
        'name' => 'Entreprise Confidentielle BTP',
        'trade_name' => 'ConfidentielBTP',
    ]);
    $this->admin = User::factory()->for($this->company)->create();
    $this->subcontractor = Subcontractor::factory()->for($this->company)->create([
        'legal_name' => 'ÉlecPro Mobile SAS',
        'contact_email' => 'contact@elecpro-mobile.fr',
    ]);

    $this->docType = DocumentType::factory()->for($this->company)->create([
        'name' => 'Attestation décennale',
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_file_size_mb' => 10,
    ]);

    $this->document = Document::factory()->for($this->company)->create([
        'subcontractor_id' => $this->subcontractor->id,
        'document_type_id' => $this->docType->id,
        'status' => DocumentStatus::Requested,
    ]);

    $this->request = new DocumentRequest([
        'subcontractor_id' => $this->subcontractor->id,
        'status' => RequestStatus::Sent,
        'due_date' => now()->addDays(10),
        'expires_at' => now()->addDays(15),
        'sent_at' => now(),
        'created_by' => $this->admin->id,
    ]);
    $this->request->company_id = $this->company->id;
    $this->token = $this->request->issueToken();
    $this->request->save();
});

it('accepte un vrai fichier PDF valide et crée la version du document', function () {
    $pdfBytes = SimplePdfGenerator::fromLines('Attestation Decennale', ['Contenu PDF valide']);
    $file = UploadedFile::fake()->createWithContent('attestation.pdf', $pdfBytes);

    Livewire::test(PortalUpload::class, ['token' => $this->token])
        ->set('files.0', $file)
        ->assertHasNoErrors()
        ->call('submitAll')
        ->assertSet('submittedSuccess', true);

    $doc = $this->document->fresh();
    expect($doc->status)->toBe(DocumentStatus::Pending)
        ->and($doc->currentVersion)->not->toBeNull()
        ->and($doc->currentVersion->mime_type)->toBe('application/pdf')
        ->and($doc->currentVersion->original_name)->toBe('attestation.pdf');

    Storage::disk('local')->assertExists($doc->currentVersion->file_path);
});

it('refuse un faux PDF (exécutable renommé en .pdf) grâce à la vérification MIME finfo', function () {
    $fakeExeBytes = "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFFThis program cannot be run in DOS mode.";
    $fakePdf = UploadedFile::fake()->createWithContent('attestation.pdf', $fakeExeBytes);

    Livewire::test(PortalUpload::class, ['token' => $this->token])
        ->set('files.0', $fakePdf)
        ->assertHasErrors(['files.0']);

    expect(DocumentVersion::acrossAllCompanies()->count())->toBe(0);
});

it('refuse un fichier de plus de 10 Mo avec le message en français attendu', function () {
    $oversizedContent = '%PDF-1.4 '.str_repeat('A', (10 * 1024 * 1024) + 2048);
    $oversizedFile = UploadedFile::fake()->createWithContent('gros-document.pdf', $oversizedContent);

    Livewire::test(PortalUpload::class, ['token' => $this->token])
        ->set('files.0', $oversizedFile)
        ->assertHasErrors(['files.0'])
        ->assertSee(PortalFileProcessor::ERROR_TOO_LARGE);

    expect(DocumentVersion::acrossAllCompanies()->count())->toBe(0);
});

it('refuse un fichier iPhone HEIC avec le message explicatif des réglages iOS', function () {
    // En-tête ISO Base Media File Format pour HEIC : 4 octets taille + "ftypheic"
    $heicBytes = "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64);
    $heicFile = UploadedFile::fake()->createWithContent('IMG_4021.HEIC', $heicBytes);

    Livewire::test(PortalUpload::class, ['token' => $this->token])
        ->set('files.0', $heicFile)
        ->assertHasErrors(['files.0'])
        ->assertSee(PortalFileProcessor::ERROR_HEIC);

    expect(DocumentVersion::acrossAllCompanies()->count())->toBe(0);
});

it('corrige l\'orientation EXIF d\'une photo JPEG, redimensionne au-delà de 2500 px et supprime les métadonnées EXIF', function () {
    // Image 3000x1500 avec Orientation EXIF = 6 (90° horaire -> doit devenir 1500x3000 puis redimensionnée à 1250x2500)
    $rawJpeg = makeJpegWithExifOrientation(3000, 1500, 6);
    expect(PortalFileProcessor::hasExifSegment($rawJpeg))->toBeTrue();

    $photo = UploadedFile::fake()->createWithContent('photo-chantier.jpg', $rawJpeg);

    Livewire::test(PortalUpload::class, ['token' => $this->token])
        ->set('files.0', $photo)
        ->assertHasNoErrors()
        ->call('submitAll')
        ->assertSet('submittedSuccess', true);

    $version = $this->document->fresh()->currentVersion;
    expect($version)->not->toBeNull()
        ->and($version->mime_type)->toBe('image/jpeg');

    $storedBytes = Storage::disk('local')->get($version->file_path);

    // 1. Les métadonnées EXIF/GPS doivent avoir été intégralement supprimées
    expect(PortalFileProcessor::hasExifSegment($storedBytes))->toBeFalse()
        ->and(str_contains($storedBytes, 'iPhone GPS Test'))->toBeFalse();

    // 2. L'orientation 6 a inversé largeur/hauteur (portrait) et la grande dimension a été ramenée à 2500 px
    $info = getimagesizefromstring($storedBytes);
    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(1250) // largeur après rotation 90° et réduction proportionnelle
        ->and($info[1])->toBe(2500); // hauteur ramenée au max 2500 px
});

it('affiche « Ce lien n\'est plus valide » sans aucune information sur l\'entreprise quand le lien est expiré ou révoqué', function () {
    $this->request->update([
        'status' => RequestStatus::Expired,
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('portal.upload', ['token' => $this->token]))
        ->assertOk()
        ->assertSee("Ce lien n'est plus valide", false)
        ->assertDontSee('Entreprise Confidentielle BTP')
        ->assertDontSee('ConfidentielBTP');
});

it('ne crée pas de doublon de version en cas de double confirmation', function () {
    $pdfBytes = SimplePdfGenerator::fromLines('Kbis', ['Exemplaire unique']);
    $file = UploadedFile::fake()->createWithContent('kbis.pdf', $pdfBytes);

    $component = Livewire::test(PortalUpload::class, ['token' => $this->token])
        ->set('files.0', $file)
        ->call('submitAll')
        ->assertSet('submittedSuccess', true);

    // Deuxième appel à submitAll (ex. double-clic ou renvoi du même fichier)
    $component->set('files.0', $file)->call('submitAll');

    expect(DocumentVersion::acrossAllCompanies()->where('document_id', $this->document->id)->count())->toBe(1);
});

it('exécute la commande artisan docpilot:lan et vérifie les limites d\'upload', function () {
    $this->artisan('docpilot:lan')
        ->assertSuccessful();

    $checkLow = UploadLimitsChecker::inspect('2M', '8M', 10);
    expect($checkLow['ok'])->toBeFalse()
        ->and($checkLow['warning'])->toContain('upload_max_filesize=2M');

    $checkOk = UploadLimitsChecker::inspect('12M', '16M', 10);
    expect($checkOk['ok'])->toBeTrue()
        ->and($checkOk['warning'])->toBeNull();
});

it('permet de prendre une photo via uploadFromMobile même sur une catégorie configurée en PDF', function () {
    $this->docType->update(['allowed_extensions' => ['pdf']]);

    $rawJpeg = makeJpegWithExifOrientation(1200, 900, 1);
    $dataUrl = 'data:image/jpeg;base64,'.base64_encode($rawJpeg);

    Livewire::test(PortalUpload::class, ['token' => $this->token])
        ->call('uploadFromMobile', 0, $dataUrl, 'photo-camera.jpg')
        ->assertHasNoErrors()
        ->assertSet('documents.0.uploaded', true)
        ->assertSet('documents.0.file_name', 'photo-camera.jpg')
        ->call('submitAll')
        ->assertSet('submittedSuccess', true);

    $version = $this->document->fresh()->currentVersion;
    expect($version)->not->toBeNull()
        ->and($version->mime_type)->toBe('image/jpeg')
        ->and($version->original_name)->toBe('photo-camera.jpg');
});


