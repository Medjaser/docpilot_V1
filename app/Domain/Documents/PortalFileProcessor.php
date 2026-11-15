<?php

namespace App\Domain\Documents;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Valide et retraite les fichiers déposés sur le portail sous-traitant :
 *  - refus des fichiers > 10 Mo avec message explicite en français ;
 *  - détection et refus (ou conversion si Imagick HEIC disponible) du format iPhone HEIC ;
 *  - vérification du vrai type MIME via finfo + signature binaire (%PDF-, JPEG, PNG) ;
 *  - correction de l'orientation EXIF des photos JPEG ;
 *  - suppression intégrale des métadonnées EXIF (GPS, appareil) pour la confidentialité ;
 *  - redimensionnement des photos très grandes (côté max 2500 px, qualité 85 %).
 */
final class PortalFileProcessor
{
    public const MAX_IMAGE_DIMENSION = 2500;

    public const JPEG_QUALITY = 85;

    public const ERROR_TOO_LARGE = 'Le fichier dépasse 10 Mo. Prenez la photo en qualité normale ou envoyez un PDF.';

    public const ERROR_HEIC = 'Format iPhone (HEIC) non accepté. Dans Réglages > Appareil photo > Formats, choisissez « Le plus compatible », ou envoyez une capture/un PDF.';

    public const ERROR_INVALID_FORMAT = 'Ce fichier n\'est pas accepté. Envoyez uniquement un document PDF ou une photo (JPG, PNG).';

    /**
     * Valide et prépare le contenu binaire à stocker.
     *
     * @param   list<string>  $allowedExtensions
     * @return array{
     *     contents: string,
     *     mime_type: string,
     *     extension: string,
     *     original_name: string,
     *     size: int,
     *     sha256: string,
     *     width: ?int,
     *     height: ?int
     * }
     *
     * @throws InvalidArgumentException avec un message en français destiné à l'utilisateur
     */
    public function processUploadedFile(
        UploadedFile $file,
        ?int $maxMb = null,
        array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'],
    ): array {
        $maxMb ??= (int) config('docpilot.max_upload_mb', 10);
        $maxBytes = $maxMb * 1024 * 1024;

        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException(self::ERROR_TOO_LARGE);
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! is_file($realPath)) {
            throw new InvalidArgumentException('Le fichier n\'a pas pu être lu. Veuillez réessayer.');
        }

        $fileSize = (int) (@filesize($realPath) ?: $file->getSize());
        if ($fileSize > $maxBytes) {
            throw new InvalidArgumentException(self::ERROR_TOO_LARGE);
        }

        $originalName = $file->getClientOriginalName() ?: 'document';
        $clientExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $clientMime = strtolower((string) $file->getClientMimeType());

        $rawBytes = (string) file_get_contents($realPath);
        if ($rawBytes === '') {
            throw new InvalidArgumentException(self::ERROR_INVALID_FORMAT);
        }

        if (strlen($rawBytes) > $maxBytes) {
            throw new InvalidArgumentException(self::ERROR_TOO_LARGE);
        }

        return $this->processRawBytes(
            rawBytes: $rawBytes,
            originalName: $originalName,
            clientExt: $clientExt,
            clientMime: $clientMime,
            allowedExtensions: $allowedExtensions,
        );
    }

    /**
     * @param  list<string>  $allowedExtensions
     * @return array{
     *     contents: string,
     *     mime_type: string,
     *     extension: string,
     *     original_name: string,
     *     size: int,
     *     sha256: string,
     *     width: ?int,
     *     height: ?int
     * }
     */
    public function processRawBytes(
        string $rawBytes,
        string $originalName = 'document',
        string $clientExt = '',
        string $clientMime = '',
        array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'],
    ): array {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string) $finfo->buffer($rawBytes));

        // 1. Détection HEIC / HEIF (extension, MIME ou signature ftyp)
        if ($this->isHeic($rawBytes, $clientExt, $clientMime, $detectedMime)) {
            $converted = $this->tryConvertHeicWithImagick($rawBytes);
            if ($converted === null) {
                throw new InvalidArgumentException(self::ERROR_HEIC);
            }
            $rawBytes = $converted;
            $detectedMime = 'image/jpeg';
            $originalName = preg_replace('/\.(heic|heif)$/i', '.jpg', $originalName) ?: ($originalName.'.jpg');
        }

        // 2. Vérification stricte du vrai contenu par finfo + octets magiques
        $normalizedAllowed = array_map('strtolower', $allowedExtensions ?: ['pdf', 'jpg', 'jpeg', 'png']);
        if (in_array('jpg', $normalizedAllowed, true) && ! in_array('jpeg', $normalizedAllowed, true)) {
            $normalizedAllowed[] = 'jpeg';
        }

        if ($detectedMime === 'application/pdf') {
            if (! $this->hasValidPdfSignature($rawBytes) || ! in_array('pdf', $normalizedAllowed, true)) {
                throw new InvalidArgumentException(self::ERROR_INVALID_FORMAT);
            }

            return [
                'contents' => $rawBytes,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'original_name' => $originalName,
                'size' => strlen($rawBytes),
                'sha256' => hash('sha256', $rawBytes),
                'width' => null,
                'height' => null,
            ];
        }

        if ($detectedMime === 'image/jpeg') {
            if (! $this->hasValidJpegSignature($rawBytes)) {
                throw new InvalidArgumentException(self::ERROR_INVALID_FORMAT);
            }
            if (! in_array('jpg', $normalizedAllowed, true) && ! in_array('jpeg', $normalizedAllowed, true)) {
                throw new InvalidArgumentException('Seul le format PDF est autorisé pour ce document.');
            }

            $processed = $this->processJpeg($rawBytes);

            return [
                'contents' => $processed['contents'],
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'original_name' => $originalName,
                'size' => strlen($processed['contents']),
                'sha256' => hash('sha256', $processed['contents']),
                'width' => $processed['width'],
                'height' => $processed['height'],
            ];
        }

        if ($detectedMime === 'image/png') {
            if (! $this->hasValidPngSignature($rawBytes)) {
                throw new InvalidArgumentException(self::ERROR_INVALID_FORMAT);
            }
            if (! in_array('png', $normalizedAllowed, true)) {
                throw new InvalidArgumentException('Seul le format PDF est autorisé pour ce document.');
            }

            $processed = $this->processPng($rawBytes);

            return [
                'contents' => $processed['contents'],
                'mime_type' => 'image/png',
                'extension' => 'png',
                'original_name' => $originalName,
                'size' => strlen($processed['contents']),
                'sha256' => hash('sha256', $processed['contents']),
                'width' => $processed['width'],
                'height' => $processed['height'],
            ];
        }

        throw new InvalidArgumentException(self::ERROR_INVALID_FORMAT);
    }

    /**
     * Corrige l'orientation EXIF d'un JPEG, redimensionne si > 2500 px et supprime tous les segments EXIF/GPS.
     *
     * @return array{contents: string, width: ?int, height: ?int}
     */
    public function processJpeg(string $rawBytes): array
    {
        $orientation = $this->readJpegExifOrientation($rawBytes);

        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($rawBytes);
            if ($image === false) {
                throw new InvalidArgumentException(self::ERROR_INVALID_FORMAT);
            }

            $image = $this->applyGdOrientation($image, $orientation);

            $width = imagesx($image);
            $height = imagesy($image);

            if (max($width, $height) > self::MAX_IMAGE_DIMENSION) {
                $ratio = self::MAX_IMAGE_DIMENSION / max($width, $height);
                $targetWidth = max(1, (int) round($width * $ratio));
                $targetHeight = max(1, (int) round($height * $ratio));

                $resized = imagecreatetruecolor($targetWidth, $targetHeight);
                imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                $image = $resized;
                $width = $targetWidth;
                $height = $targetHeight;
            }

            ob_start();
            imagejpeg($image, null, self::JPEG_QUALITY);
            $cleanBytes = (string) ob_get_clean();

            return [
                'contents' => $cleanBytes,
                'width' => $width,
                'height' => $height,
            ];
        }

        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            $imagick = new \Imagick;
            $imagick->readImageBlob($rawBytes);
            $this->applyImagickOrientation($imagick, $orientation);
            $imagick->stripImage();

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            if (max($width, $height) > self::MAX_IMAGE_DIMENSION) {
                $imagick->thumbnailImage(self::MAX_IMAGE_DIMENSION, self::MAX_IMAGE_DIMENSION, true);
                $width = $imagick->getImageWidth();
                $height = $imagick->getImageHeight();
            }

            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(self::JPEG_QUALITY);
            $cleanBytes = $imagick->getImageBlob();
            $imagick->clear();

            return [
                'contents' => $cleanBytes,
                'width' => $width,
                'height' => $height,
            ];
        }

        Log::warning('DocPilot : ni GD ni Imagick n\'est installé ; la photo JPEG est stockée sans retraitement EXIF.');

        return [
            'contents' => $rawBytes,
            'width' => null,
            'height' => null,
        ];
    }

    /**
     * @return array{contents: string, width: ?int, height: ?int}
     */
    public function processPng(string $rawBytes): array
    {
        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($rawBytes);
            if ($image === false) {
                throw new InvalidArgumentException(self::ERROR_INVALID_FORMAT);
            }

            $width = imagesx($image);
            $height = imagesy($image);

            if (max($width, $height) > self::MAX_IMAGE_DIMENSION) {
                $ratio = self::MAX_IMAGE_DIMENSION / max($width, $height);
                $targetWidth = max(1, (int) round($width * $ratio));
                $targetHeight = max(1, (int) round($height * $ratio));

                $resized = imagecreatetruecolor($targetWidth, $targetHeight);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                $image = $resized;
                $width = $targetWidth;
                $height = $targetHeight;
            } else {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            ob_start();
            imagepng($image, null, 6);
            $cleanBytes = (string) ob_get_clean();

            return [
                'contents' => $cleanBytes,
                'width' => $width,
                'height' => $height,
            ];
        }

        return [
            'contents' => $rawBytes,
            'width' => null,
            'height' => null,
        ];
    }

    /**
     * Lit le tag EXIF Orientation (0x0112) directement depuis les octets JPEG (compatible avec ou sans ext-exif).
     */
    public function readJpegExifOrientation(string $bytes): int
    {
        $len = strlen($bytes);
        if ($len < 12 || substr($bytes, 0, 2) !== "\xFF\xD8") {
            return 1;
        }

        $offset = 2;
        while ($offset + 4 <= $len) {
            if ($bytes[$offset] !== "\xFF") {
                break;
            }

            $marker = ord($bytes[$offset + 1]);
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            $segLen = unpack('n', substr($bytes, $offset + 2, 2))[1];
            if ($segLen < 2 || $offset + 2 + $segLen > $len) {
                break;
            }

            // Segment APP1 (0xE1) commençant par "Exif\0\0"
            if ($marker === 0xE1 && $segLen >= 14 && substr($bytes, $offset + 4, 6) === "Exif\x00\x00") {
                $tiff = substr($bytes, $offset + 10, $segLen - 8);
                $orientation = $this->parseTiffOrientation($tiff);
                if ($orientation >= 1 && $orientation <= 8) {
                    return $orientation;
                }
            }

            $offset += 2 + $segLen;
        }

        return 1;
    }

    /**
     * Vérifie si un flux JPEG contient un segment APP1 Exif.
     */
    public static function hasExifSegment(string $bytes): bool
    {
        $len = strlen($bytes);
        if ($len < 12 || substr($bytes, 0, 2) !== "\xFF\xD8") {
            return false;
        }

        $offset = 2;
        while ($offset + 4 <= $len && $bytes[$offset] === "\xFF") {
            $marker = ord($bytes[$offset + 1]);
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }
            $segLen = unpack('n', substr($bytes, $offset + 2, 2))[1];
            if ($segLen < 2 || $offset + 2 + $segLen > $len) {
                break;
            }
            if ($marker === 0xE1 && substr($bytes, $offset + 4, 6) === "Exif\x00\x00") {
                return true;
            }
            $offset += 2 + $segLen;
        }

        return false;
    }

    private function parseTiffOrientation(string $tiff): int
    {
        if (strlen($tiff) < 8) {
            return 1;
        }

        $byteOrder = substr($tiff, 0, 2);
        $shortFormat = match ($byteOrder) {
            'II' => 'v', // Little-endian
            'MM' => 'n', // Big-endian
            default => null,
        };
        $longFormat = $byteOrder === 'II' ? 'V' : 'N';

        if ($shortFormat === null) {
            return 1;
        }

        $ifdOffset = unpack($longFormat, substr($tiff, 4, 4))[1];
        if ($ifdOffset + 2 > strlen($tiff)) {
            return 1;
        }

        $numEntries = unpack($shortFormat, substr($tiff, $ifdOffset, 2))[1];
        $pos = $ifdOffset + 2;

        for ($i = 0; $i < $numEntries; $i++) {
            if ($pos + 12 > strlen($tiff)) {
                break;
            }
            $tag = unpack($shortFormat, substr($tiff, $pos, 2))[1];
            if ($tag === 0x0112) {
                return (int) unpack($shortFormat, substr($tiff, $pos + 8, 2))[1];
            }
            $pos += 12;
        }

        return 1;
    }

    private function applyGdOrientation(\GdImage $image, int $orientation): \GdImage
    {
        return match ($orientation) {
            2 => (function (\GdImage $img): \GdImage {
                imageflip($img, IMG_FLIP_HORIZONTAL);

                return $img;
            })($image),
            3 => imagerotate($image, 180, 0) ?: $image,
            4 => (function (\GdImage $img): \GdImage {
                imageflip($img, IMG_FLIP_VERTICAL);

                return $img;
            })($image),
            5 => (function (\GdImage $img): \GdImage {
                imageflip($img, IMG_FLIP_HORIZONTAL);

                return imagerotate($img, 90, 0) ?: $img;
            })($image),
            6 => imagerotate($image, -90, 0) ?: $image,
            7 => (function (\GdImage $img): \GdImage {
                imageflip($img, IMG_FLIP_HORIZONTAL);

                return imagerotate($img, -90, 0) ?: $img;
            })($image),
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private function applyImagickOrientation(\Imagick $imagick, int $orientation): void
    {
        switch ($orientation) {
            case 2:
                $imagick->flopImage();
                break;
            case 3:
                $imagick->rotateImage('#000', 180);
                break;
            case 4:
                $imagick->flipImage();
                break;
            case 5:
                $imagick->flopImage();
                $imagick->rotateImage('#000', -90);
                break;
            case 6:
                $imagick->rotateImage('#000', 90);
                break;
            case 7:
                $imagick->flopImage();
                $imagick->rotateImage('#000', 90);
                break;
            case 8:
                $imagick->rotateImage('#000', -90);
                break;
        }
        $imagick->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    private function isHeic(string $bytes, string $clientExt, string $clientMime, string $detectedMime): bool
    {
        if (in_array($clientExt, ['heic', 'heif'], true)) {
            return true;
        }

        if (str_contains($clientMime, 'heic') || str_contains($clientMime, 'heif')) {
            return true;
        }

        if (str_contains($detectedMime, 'heic') || str_contains($detectedMime, 'heif')) {
            return true;
        }

        if (strlen($bytes) >= 12 && substr($bytes, 4, 4) === 'ftyp') {
            $brand = strtolower(substr($bytes, 8, 4));
            if (in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true)) {
                return true;
            }
        }

        return false;
    }

    private function tryConvertHeicWithImagick(string $bytes): ?string
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $formats = array_map('strtoupper', \Imagick::queryFormats());
            if (! in_array('HEIC', $formats, true) && ! in_array('HEIF', $formats, true)) {
                return null;
            }

            $imagick = new \Imagick;
            $imagick->readImageBlob($bytes);
            $imagick->stripImage();
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(self::JPEG_QUALITY);
            $jpeg = $imagick->getImageBlob();
            $imagick->clear();

            return $jpeg ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasValidPdfSignature(string $bytes): bool
    {
        return str_contains(substr($bytes, 0, 1024), '%PDF-');
    }

    private function hasValidJpegSignature(string $bytes): bool
    {
        return strlen($bytes) >= 4 && substr($bytes, 0, 3) === "\xFF\xD8\xFF";
    }

    private function hasValidPngSignature(string $bytes): bool
    {
        return strlen($bytes) >= 8 && substr($bytes, 0, 8) === "\x89PNG\r\n\x1A\n";
    }
}

