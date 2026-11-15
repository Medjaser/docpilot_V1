<?php

namespace App\Domain\Company;

use App\Models\Company;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Logo d'une entreprise : redimensionné (512 px max) puis stocké sur le disque docpilot.branding.disk
 * (s3 par défaut) dans companies/{id}/branding/logo-{uuid}.{ext}. Le SVG est exclu (risque XSS).
 */
class CompanyLogoStorage
{
    public const MAX_DIMENSION = 512;

    public const MAX_KB = 2048;

    private const EXTENSIONS_BY_MIME = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * Retourne les attributs logo_uuid / logo_path du nouveau fichier ;
     * l'ancien fichier est à supprimer par l'appelant une fois la base à jour.
     *
     * @return array{logo_uuid: string, logo_path: string}
     */
    public function store(Company $company, UploadedFile $file): array
    {
        $extension = self::EXTENSIONS_BY_MIME[$file->getMimeType()]
            ?? throw new InvalidArgumentException('Format de logo non autorisé.');

        $uuid = (string) Str::uuid();
        $path = "companies/{$company->id}/branding/logo-{$uuid}.{$extension}";

        // Le disque s3 est configuré avec throw => false : un échec renvoie false sans exception
        if (! self::disk()->put($path, $this->resize($file->get(), $extension), ['visibility' => 'private'])) {
            throw new RuntimeException("Écriture du logo impossible sur le disque « ".config('docpilot.branding.disk')." ».");
        }

        return ['logo_uuid' => $uuid, 'logo_path' => $path];
    }

    public static function mimeTypeFor(string $path): string
    {
        $mimes = array_flip(self::EXTENSIONS_BY_MIME);

        return $mimes[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    public function delete(?string $path): void
    {
        if ($path) {
            self::disk()->delete($path);
        }
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('docpilot.branding.disk'));
    }

    private function resize(string $contents, string $extension): string
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new InvalidArgumentException('Image illisible.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($extension === 'jpg') {
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        } else {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        match ($extension) {
            'png' => imagepng($target, null, 6),
            'webp' => imagewebp($target, null, 85),
            default => imagejpeg($target, null, 85),
        };

        return (string) ob_get_clean();
    }
}
