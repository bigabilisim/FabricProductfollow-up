<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;

final class PwaController
{
    public function manifest(): void
    {
        $config = Config::load();
        $siteName = (string) $config->get('app.site_name', 'Fabrika QR Bakim Takip');
        $logoPath = (string) $config->get('app.logo_path', '');
        $icon = $logoPath !== '' ? app_path($logoPath) : app_path('/assets/pwa-icon.svg');

        header('Content-Type: application/manifest+json; charset=UTF-8');
        echo json_encode([
            'name' => $siteName,
            'short_name' => substr($siteName, 0, 24),
            'description' => 'QR cihaz kimligi ve bakim takip sistemi',
            'start_url' => app_path('/admin'),
            'scope' => app_path('/'),
            'display' => 'standalone',
            'background_color' => '#f4f7f6',
            'theme_color' => '#102522',
            'orientation' => 'any',
            'icons' => [
                [
                    'src' => $icon,
                    'sizes' => 'any',
                    'type' => $logoPath !== '' ? $this->mimeFromPath($logoPath) : 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function offline(): void
    {
        view('offline', ['title' => 'Cevrimdisi']);
    }

    private function mimeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
