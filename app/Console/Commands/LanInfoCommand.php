<?php

namespace App\Console\Commands;

use App\Support\UploadLimitsChecker;
use Illuminate\Console\Command;

class LanInfoCommand extends Command
{
    protected $signature = 'docpilot:lan {--port=8000 : Port du serveur HTTP local}';

    protected $description = 'Affiche la configuration réseau local (Wi-Fi) pour tester le portail de dépôt sur téléphone';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $ip = $this->detectLanIp();
        $expectedAppUrl = "http://{$ip}:{$port}";
        $currentAppUrl = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->components->info('Configuration réseau local DocPilot (Test sur téléphone)');

        $this->line("  • <options=bold>IP locale détectée (Wi-Fi / LAN) :</> <fg=green>{$ip}</>");
        $this->line("  • <options=bold>APP_URL actuel :</> <fg=yellow>{$currentAppUrl}</>");
        $this->newLine();

        if ($currentAppUrl !== $expectedAppUrl) {
            $this->components->warn("Mettez à jour votre fichier .env avec : APP_URL={$expectedAppUrl}");
            $this->line("    Puis videz le cache de configuration : <fg=cyan>php artisan config:clear</>");
        } else {
            $this->components->info("APP_URL est correctement configuré sur {$expectedAppUrl}");
        }

        $manifestPath = public_path('build/manifest.json');
        if (! is_dir(public_path('build')) || ! is_file($manifestPath)) {
            $this->components->error('Le dossier public/build est absent. Lancez impérativement : npm run build');
        } else {
            $this->components->info('Assets compilés présents dans public/build (manifest.json OK).');
        }

        $limits = UploadLimitsChecker::inspect();
        if (! $limits['ok'] && $limits['warning']) {
            $this->components->warn($limits['warning']);
        } else {
            $this->components->info(
                "Limites d'upload PHP OK (upload_max_filesize={$limits['upload_max_filesize']}, post_max_size={$limits['post_max_size']})."
            );
        }

        $this->line('  <options=bold>Commandes à lancer dans deux terminaux :</>');
        $this->line("    1) <fg=cyan>php artisan serve --host=0.0.0.0 --port={$port}</>");
        $this->line('    2) <fg=cyan>php artisan queue:work</>');
        $this->newLine();
        $this->line("  Depuis votre téléphone (connecté au même Wi-Fi), ouvrez : <fg=green>{$expectedAppUrl}</>");
        $this->newLine();

        return self::SUCCESS;
    }

    public static function publicBaseUrl(): string
    {
        $url = rtrim((string) config('app.url'), '/');

        if (! str_contains($url, '://localhost') && ! str_contains($url, '://127.0.0.1')) {
            return $url;
        }

        $envPath = base_path('.env');
        if (is_file($envPath)) {
            $envContent = (string) @file_get_contents($envPath);
            if (preg_match('/^APP_URL=(.+)$/m', $envContent, $m)) {
                $envUrl = rtrim(trim($m[1], " \t\n\r\0\x0B\"'"), '/');
                if ($envUrl !== '' && ! str_contains($envUrl, '://localhost') && ! str_contains($envUrl, '://127.0.0.1')) {
                    return $envUrl;
                }
            }
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'http';
        $port = $parsed['port'] ?? 8000;
        $ip = (new self)->detectLanIp();

        return "{$scheme}://{$ip}:{$port}";
    }

    public static function normalizeUrl(string $url): string
    {
        if (! str_contains($url, '://localhost') && ! str_contains($url, '://127.0.0.1')) {
            return $url;
        }

        $base = self::publicBaseUrl();

        return (string) preg_replace('#^https?://(localhost|127\.0\.0\.1)(:\d+)?#i', $base, $url);
    }

    public function detectLanIp(): string
    {
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('ipconfig');
            if (is_string($output) && preg_match_all('/IPv4[^:]*:\s*([0-9]{1,3}(?:\.[0-9]{1,3}){3})/i', $output, $matches)) {
                foreach ($matches[1] as $ip) {
                    $candidates[] = $ip;
                }
            }
        }

        $hostIps = @gethostbynamel((string) gethostname()) ?: [];
        foreach ($hostIps as $ip) {
            $candidates[] = $ip;
        }

        // Priorité aux adresses LAN privées classiques (192.168.x.x, 10.x.x.x, 172.16-31.x.x) hors interfaces virtuelles .1
        foreach ($candidates as $ip) {
            if (preg_match('/^192\.168\.\d+\.\d+$/', $ip) && ! str_ends_with($ip, '.1')) {
                return $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && ! str_starts_with($ip, '127.')
                && ! str_starts_with($ip, '169.254.')
            ) {
                return $ip;
            }
        }

        return '192.168.1.10';
    }
}

