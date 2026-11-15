<?php

namespace App\Support;

final class UploadLimitsChecker
{
    /**
     * Vérifie si upload_max_filesize et post_max_size sont au moins égaux à docpilot.max_upload_mb.
     *
     * @return array{
     *     ok: bool,
     *     required_mb: int,
     *     upload_max_filesize: string,
     *     upload_max_bytes: int,
     *     post_max_size: string,
     *     post_max_bytes: int,
     *     php_ini_path: string,
     *     warning: ?string
     * }
     */
    public static function inspect(?string $uploadMaxRaw = null, ?string $postMaxRaw = null, ?int $requiredMb = null): array
    {
        $requiredMb ??= (int) config('docpilot.max_upload_mb', 10);
        $requiredBytes = $requiredMb * 1024 * 1024;

        $uploadMaxRaw ??= (string) ini_get('upload_max_filesize');
        $postMaxRaw ??= (string) ini_get('post_max_size');

        $uploadBytes = self::parseIniBytes($uploadMaxRaw);
        $postBytes = self::parseIniBytes($postMaxRaw);

        // post_max_size = 0 signifie illimité en PHP
        $uploadOk = $uploadBytes >= $requiredBytes;
        $postOk = $postBytes === 0 || $postBytes >= $requiredBytes;
        $ok = $uploadOk && $postOk;

        $iniPath = php_ini_loaded_file() ?: 'php.ini (chemin non détecté)';

        $warning = null;
        if (! $ok) {
            $warning = sprintf(
                'Limites PHP insuffisantes pour DocPilot : upload_max_filesize=%s et post_max_size=%s (minimum requis : %dM). Modifiez %s.',
                $uploadMaxRaw,
                $postMaxRaw,
                $requiredMb,
                $iniPath,
            );
        }

        return [
            'ok' => $ok,
            'required_mb' => $requiredMb,
            'upload_max_filesize' => $uploadMaxRaw,
            'upload_max_bytes' => $uploadBytes,
            'post_max_size' => $postMaxRaw,
            'post_max_bytes' => $postBytes,
            'php_ini_path' => $iniPath,
            'warning' => $warning,
        ];
    }

    public static function parseIniBytes(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }

        $unit = strtolower(substr($trimmed, -1));
        $number = (float) $trimmed;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}

