<?php

namespace App\Http\Controllers;

use App\Domain\Documents\SimplePdfGenerator;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $document = Document::with(['subcontractor', 'documentType', 'currentVersion', 'versions'])->findOrFail($id);
        Gate::authorize('download', $document);

        $versionId = $request->query('version');
        $version = $versionId
            ? $document->versions->firstWhere('id', (int) $versionId)
            : $document->currentVersion;

        $typeName = $document->documentType?->name ?? 'Document';
        $subName = $document->subcontractor?->legal_name ?? 'Sous-traitant';

        $isInline = $request->boolean('inline');

        if ($version) {
            $disk = $version->disk ?: config('docpilot.uploads.disk', 'documents');
            if (Storage::disk($disk)->exists($version->file_path)) {
                $mime = $version->mime_type ?: 'application/pdf';
                $downloadName = $version->original_name ?: (Str::slug("{$subName}-{$typeName}-v{$version->version_number}").'.pdf');

                if ($isInline) {
                    return Storage::disk($disk)->response($version->file_path, $downloadName, [
                        'Content-Type' => $mime,
                    ]);
                }

                return Storage::disk($disk)->download($version->file_path, $downloadName, [
                    'Content-Type' => $mime,
                ]);
            }
        }

        // Génération d'un PDF de prévisualisation valide pour les données de démonstration
        $pdfBytes = SimplePdfGenerator::fromLines("{$typeName} - {$subName}", [
            'Entreprise cliente : '.(auth()->user()?->company?->name ?? 'DocPilot'),
            'Sous-traitant : '.$subName.' (SIRET '.($document->subcontractor?->registration_number ?? '-').')',
            'Categorie : '.$typeName,
            'Version : '.($version ? 'v'.$version->version_number : 'v1'),
            'Fichier : '.($version?->original_name ?? $typeName.'.pdf'),
            'Statut actuel : '.$document->status->label(),
            'Date d\'emission : '.($document->issue_date?->format('d/m/Y') ?? '-'),
            'Date d\'expiration : '.($document->expiration_date?->format('d/m/Y') ?? 'Sans echeance'),
            'SHA-256 : '.($version?->sha256 ?? '-'),
            '------------------------------------------------------------------------',
            'Document stocke et controle via la plateforme securisee DocPilot.',
        ]);

        $filename = Str::slug("{$subName}-{$typeName}").'.pdf';
        $disposition = $isInline ? 'inline' : 'attachment';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
