<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class TemplateService
{
    private const TYPES = ['mail', 'report'];
    private static bool $schemaChecked = false;
    private static bool $defaultsChecked = false;

    public function all(string $type): array
    {
        $type = $this->type($type);
        $this->ensureDefaults();

        $statement = Database::pdo()->prepare('SELECT * FROM content_templates WHERE type = :type ORDER BY is_system DESC, name ASC');
        $statement->execute(['type' => $type]);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $this->ensureDefaults();
        $statement = Database::pdo()->prepare('SELECT * FROM content_templates WHERE id = :id');
        $statement->execute(['id' => $id]);
        $template = $statement->fetch();

        return $template ?: null;
    }

    public function findBySlug(string $type, string $slug): ?array
    {
        $type = $this->type($type);
        $this->ensureDefaults();
        $statement = Database::pdo()->prepare('SELECT * FROM content_templates WHERE type = :type AND slug = :slug AND active = 1 LIMIT 1');
        $statement->execute(['type' => $type, 'slug' => $slug]);
        $template = $statement->fetch();

        return $template ?: null;
    }

    public function create(array $data): int
    {
        $data = $this->normalize($data);
        $data['created_at'] = date('Y-m-d H:i:s');

        Database::pdo()->prepare('
            INSERT INTO content_templates
                (type, slug, name, subject, html, css, project_json, is_system, active, created_at)
            VALUES
                (:type, :slug, :name, :subject, :html, :css, :project_json, :is_system, :active, :created_at)
        ')->execute($data);

        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $data = $this->normalize($data);
        $data['id'] = $id;
        $data['updated_at'] = date('Y-m-d H:i:s');

        Database::pdo()->prepare('
            UPDATE content_templates SET
                type = :type,
                slug = :slug,
                name = :name,
                subject = :subject,
                html = :html,
                css = :css,
                project_json = :project_json,
                is_system = :is_system,
                active = :active,
                updated_at = :updated_at
            WHERE id = :id
        ')->execute($data);
    }

    public function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM content_templates WHERE id = :id AND is_system = 0')->execute(['id' => $id]);
    }

    public function renderMail(string $slug, array $variables, string $fallbackSubject, string $fallbackHtml): array
    {
        $template = $this->findBySlug('mail', $slug);
        if (!$template) {
            return ['subject' => $fallbackSubject, 'html' => $fallbackHtml];
        }

        $html = $this->renderString((string) $template['html'], $variables);
        $css = trim((string) ($template['css'] ?? ''));
        if ($css !== '') {
            $html = '<style>' . $css . '</style>' . $html;
        }

        return [
            'subject' => $this->renderString((string) ($template['subject'] ?: $fallbackSubject), $variables, false),
            'html' => $html,
        ];
    }

    public function renderTemplate(array $template, array $variables): array
    {
        $type = (string) ($template['type'] ?? 'mail');
        $name = (string) ($template['name'] ?? 'Sablon');
        $subject = $type === 'mail'
            ? (string) (($template['subject'] ?? '') ?: 'Test Maili: ' . $name)
            : 'Test Raporu: ' . $name;

        $html = $this->renderString((string) ($template['html'] ?? ''), $variables);
        $css = trim((string) ($template['css'] ?? ''));
        if ($css !== '') {
            $html = '<style>' . $css . '</style>' . $html;
        }

        return [
            'subject' => $this->renderString($subject, $variables, false),
            'html' => $html,
        ];
    }

    public function sampleVariables(): array
    {
        return [
            'device_code' => 'ANA-TR-2026-55',
            'serial_number' => 'SN-TEST-001',
            'maintenance_date' => date('Y-m-d', strtotime('+7 days') ?: time()),
            'days_left' => '7',
            'status' => 'Bekliyor',
            'report_date' => date('Y-m-d'),
            'hazard_note' => 'Bakimin yapilmamasi ariza riskini, plansiz durusu ve kalite problemlerini artirabilir.',
            'done_url' => url('/maintenance/respond?token=test&status=done'),
            'not_done_url' => url('/maintenance/respond?token=test&status=not_done'),
            'rescheduled_url' => url('/maintenance/respond?token=test&status=rescheduled'),
            'ack_url' => url('/maintenance/read-ack?token=test'),
        ];
    }

    public function ensureDefaults(): void
    {
        $this->ensureSchema();
        if (self::$defaultsChecked) {
            return;
        }

        foreach ($this->defaults() as $template) {
            $statement = Database::pdo()->prepare('SELECT id FROM content_templates WHERE type = :type AND slug = :slug LIMIT 1');
            $statement->execute(['type' => $template['type'], 'slug' => $template['slug']]);
            if ($statement->fetchColumn()) {
                continue;
            }

            $this->create($template + ['is_system' => 1, 'active' => 1, 'project_json' => null]);
        }

        self::$defaultsChecked = true;
    }

    private function normalize(array $data): array
    {
        $type = $this->type((string) ($data['type'] ?? 'mail'));
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Sablon adi bos olamaz.');
        }

        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?: '';
        $slug = trim($slug, '-_');
        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($name)) ?: '';
            $slug = trim($slug, '-_');
        }

        if ($slug === '') {
            throw new RuntimeException('Sablon kodu olusturulamadi.');
        }

        $html = (string) ($data['html'] ?? '');
        if (trim($html) === '') {
            $html = '<p>Yeni sablon</p>';
        }

        return [
            'type' => $type,
            'slug' => $slug,
            'name' => $name,
            'subject' => $type === 'mail' ? trim((string) ($data['subject'] ?? '')) : null,
            'html' => $html,
            'css' => (string) ($data['css'] ?? ''),
            'project_json' => (string) ($data['project_json'] ?? ''),
            'is_system' => !empty($data['is_system']) ? 1 : 0,
            'active' => !empty($data['active']) ? 1 : 0,
        ];
    }

    private function renderString(string $template, array $variables, bool $html = true): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $value = (string) $value;
            $replacements['{{' . $key . '}}'] = $html ? e($value) : $value;
            $replacements['{{ ' . $key . ' }}'] = $html ? e($value) : $value;
        }

        return strtr($template, $replacements);
    }

    private function type(string $type): string
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException('Gecersiz sablon tipi.');
        }

        return $type;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        Database::pdo()->exec("
            CREATE TABLE IF NOT EXISTS content_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(40) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                name VARCHAR(190) NOT NULL,
                subject VARCHAR(255) NULL,
                html MEDIUMTEXT NOT NULL,
                css MEDIUMTEXT NULL,
                project_json MEDIUMTEXT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_content_template_slug (type, slug),
                INDEX idx_content_templates_type_active (type, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$schemaChecked = true;
    }

    private function defaults(): array
    {
        return [
            [
                'type' => 'mail',
                'slug' => 'maintenance_upcoming',
                'name' => 'Bakim Yaklasiyor',
                'subject' => '{{device_code}} bakimi yaklasiyor',
                'html' => '<section style="font-family:Arial,sans-serif;color:#17211f;"><h2>Bakim Yaklasiyor</h2><p><strong>{{device_code}}</strong> kodlu cihaz icin bakim tarihi yaklasiyor.</p><p>Planlanan bakim tarihi: <strong>{{maintenance_date}}</strong></p><p>Kalan gun: <strong>{{days_left}}</strong></p></section>',
                'css' => '',
            ],
            [
                'type' => 'mail',
                'slug' => 'maintenance_due',
                'name' => 'Bakim Gunu Sorgusu',
                'subject' => '{{device_code}} bakimi yapildi mi?',
                'html' => '<section style="font-family:Arial,sans-serif;color:#17211f;"><h2>Bakim Gunu Geldi</h2><p><strong>{{device_code}}</strong> kodlu cihaz icin bakim gunu geldi.</p><p>Lutfen 48 saat icinde asagidaki butonlardan biriyle durum bildirin.</p><p><a href="{{done_url}}" style="display:inline-block;padding:10px 14px;background:#087443;color:#fff;text-decoration:none;border-radius:6px;">Yapildi</a> <a href="{{not_done_url}}" style="display:inline-block;padding:10px 14px;background:#b42318;color:#fff;text-decoration:none;border-radius:6px;">Yapilmadi</a> <a href="{{rescheduled_url}}" style="display:inline-block;padding:10px 14px;background:#b45309;color:#fff;text-decoration:none;border-radius:6px;">Baska zamana planlandi</a></p></section>',
                'css' => '',
            ],
            [
                'type' => 'mail',
                'slug' => 'maintenance_no_response',
                'name' => 'Bakim Cevabi Alinmadi',
                'subject' => '{{device_code}} bakim cevabi alinmadi',
                'html' => '<section style="font-family:Arial,sans-serif;color:#17211f;"><h2>Cevap Alinamadi</h2><p><strong>{{device_code}}</strong> bakimi icin 48 saatlik cevap suresi doldu.</p><p>Bakimin durumu icin teknik sorumluya ulasilmasi onerilir.</p></section>',
                'css' => '',
            ],
            [
                'type' => 'mail',
                'slug' => 'maintenance_hazard',
                'name' => 'Bakim Riski Bildirimi',
                'subject' => '{{device_code}} bakim riski bildirimi',
                'html' => '<section style="font-family:Arial,sans-serif;color:#17211f;"><h2>Bakim Riski</h2><p><strong>{{device_code}}</strong> kodlu cihaz icin bakim yapilmadi olarak isaretlendi.</p><p>{{hazard_note}}</p><p><a href="{{ack_url}}" style="display:inline-block;padding:10px 14px;background:#102522;color:#fff;text-decoration:none;border-radius:6px;">Okudum, bilgi sahibiyim</a></p></section>',
                'css' => '',
            ],
            [
                'type' => 'report',
                'slug' => 'maintenance_summary',
                'name' => 'Bakim Ozet Raporu',
                'subject' => null,
                'html' => '<section style="font-family:Arial,sans-serif;color:#17211f;"><h1>Bakim Ozet Raporu</h1><p>Bu alan rapor tasarimi icin GrapesJS ile duzenlenebilir.</p><table style="width:100%;border-collapse:collapse;"><tr><th style="border-bottom:1px solid #dce5e1;text-align:left;padding:8px;">Cihaz</th><th style="border-bottom:1px solid #dce5e1;text-align:left;padding:8px;">Durum</th><th style="border-bottom:1px solid #dce5e1;text-align:left;padding:8px;">Tarih</th></tr><tr><td style="padding:8px;">{{device_code}}</td><td style="padding:8px;">{{status}}</td><td style="padding:8px;">{{report_date}}</td></tr></table></section>',
                'css' => '',
            ],
        ];
    }
}
