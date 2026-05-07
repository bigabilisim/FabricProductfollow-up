<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditLogger;
use App\Services\Mailer;
use App\Services\TemplateService;
use Throwable;

final class TemplateController
{
    private const MAX_TEST_RECIPIENTS = 20;

    private TemplateService $templates;

    public function __construct()
    {
        $this->templates = new TemplateService();
    }

    public function index(): void
    {
        require_auth();
        $type = $this->typeFromRequest();

        view('admin/templates/index', [
            'title' => $this->title($type),
            'type' => $type,
            'templates' => $this->templates->all($type),
        ]);
    }

    public function create(): void
    {
        require_auth();
        $type = $this->typeFromRequest();

        view('admin/templates/form', [
            'title' => 'Yeni ' . $this->title($type),
            'type' => $type,
            'template' => [
                'id' => null,
                'type' => $type,
                'slug' => '',
                'name' => '',
                'subject' => '',
                'html' => $type === 'mail' ? '<p>Mail icerigi</p>' : '<h1>Rapor Basligi</h1><p>Rapor icerigi</p>',
                'css' => '',
                'project_json' => '',
                'active' => 1,
                'is_system' => 0,
            ],
        ]);
    }

    public function store(): void
    {
        require_auth();
        verify_csrf();

        try {
            $id = $this->templates->create($this->dataFromPost());
            AuditLogger::log('template.created', 'id=' . $id);
            flash('Sablon olusturuldu.');
            redirect('/admin/templates/' . $id . '/edit');
        } catch (Throwable $exception) {
            flash('Sablon olusturulamadi: ' . $exception->getMessage(), 'error');
            redirect('/admin/templates/create?type=' . urlencode((string) ($_POST['type'] ?? 'mail')));
        }
    }

    public function edit(string $id): void
    {
        require_auth();
        $template = $this->find((int) $id);

        view('admin/templates/form', [
            'title' => 'Sablon Duzenle',
            'type' => (string) $template['type'],
            'template' => $template,
        ]);
    }

    public function update(string $id): void
    {
        require_auth();
        verify_csrf();

        try {
            $template = $this->find((int) $id);
            $data = $this->dataFromPost();
            $data['is_system'] = (int) $template['is_system'];
            $this->templates->update((int) $id, $data);
            AuditLogger::log('template.updated', 'id=' . (int) $id);
            flash('Sablon kaydedildi.');
        } catch (Throwable $exception) {
            flash('Sablon kaydedilemedi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/templates/' . (int) $id . '/edit');
    }

    public function testPlatform(): void
    {
        require_auth();

        $templates = $this->templates->all('mail');
        $selectedTemplate = $this->selectedMailTemplate($templates, (int) ($_GET['template_id'] ?? 0));
        $variables = $this->templates->sampleVariables();

        view('admin/templates/test_platform', [
            'title' => 'Mail Test Platformu',
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'rendered' => $selectedTemplate ? $this->templates->renderTemplate($selectedTemplate, $variables) : null,
            'variables' => $variables,
        ]);
    }

    public function sendTestPlatform(): void
    {
        require_auth();
        verify_csrf();

        $templateId = (int) ($_POST['template_id'] ?? 0);
        try {
            $template = $this->find($templateId);
            if ((string) ($template['type'] ?? '') !== 'mail') {
                throw new \RuntimeException('Sadece mail sablonlari test edilebilir.');
            }

            $emails = $this->emailsFromText((string) ($_POST['test_emails'] ?? ''));
            if ($emails === []) {
                throw new \RuntimeException('Gecerli test mail adresi girin.');
            }

            $rendered = $this->templates->renderTemplate($template, $this->templates->sampleVariables());
            $mailer = new Mailer();
            $sent = [];
            $failed = [];

            foreach ($emails as $email) {
                try {
                    $mailer->send($email, '[TEST] ' . $rendered['subject'], $rendered['html']);
                    $sent[] = $email;
                } catch (Throwable) {
                    $failed[] = $email;
                }
            }

            AuditLogger::log(
                'template.test_platform.sent',
                'id=' . $templateId . ' sent=' . count($sent) . ' failed=' . count($failed)
            );

            if ($failed !== []) {
                $message = count($sent) . ' test maili gonderildi. Gonderilemeyen adresler: ' . implode(', ', $failed);
                flash($message, 'error');
            } else {
                flash('Test maili gonderildi: ' . implode(', ', $sent));
            }
        } catch (Throwable $exception) {
            flash('Test maili gonderilemedi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/templates/test-platform?template_id=' . $templateId);
    }

    public function testMail(string $id): void
    {
        require_auth();
        verify_csrf();

        try {
            $savedTemplate = $this->find((int) $id);
            $email = trim((string) ($_POST['test_email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Gecerli bir test mail adresi girin.');
            }

            $template = array_merge($savedTemplate, $this->dataFromPost());
            $template['id'] = (int) $id;
            $rendered = $this->templates->renderTemplate($template, $this->templates->sampleVariables());
            (new Mailer())->send($email, '[TEST] ' . $rendered['subject'], $rendered['html']);

            AuditLogger::log('template.test_mail.sent', 'id=' . (int) $id . ' to=' . $email);
            flash('Test maili gonderildi: ' . $email);
        } catch (Throwable $exception) {
            flash('Test maili gonderilemedi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/templates/' . (int) $id . '/edit');
    }

    public function delete(string $id): void
    {
        require_auth();
        verify_csrf();

        $type = 'mail';
        try {
            $template = $this->find((int) $id);
            $type = (string) $template['type'];
            $this->templates->delete((int) $id);
            AuditLogger::log('template.deleted', 'id=' . (int) $id);
            flash('Sablon silindi.');
        } catch (Throwable $exception) {
            flash('Sablon silinemedi: ' . $exception->getMessage(), 'error');
        }

        redirect('/admin/templates?type=' . urlencode($type));
    }

    public function preview(string $id): void
    {
        require_auth();
        $template = $this->find((int) $id);

        view('admin/templates/preview', [
            'title' => 'Sablon Onizleme',
            'template' => $template,
        ]);
    }

    private function find(int $id): array
    {
        $template = $this->templates->find($id);
        if (!$template) {
            throw new \RuntimeException('Sablon bulunamadi.');
        }

        return $template;
    }

    private function selectedMailTemplate(array $templates, int $templateId): ?array
    {
        foreach ($templates as $template) {
            if ($templateId > 0 && (int) $template['id'] === $templateId) {
                return $template;
            }
        }

        return $templates[0] ?? null;
    }

    private function emailsFromText(string $value): array
    {
        $tokens = preg_split('/[\s,;]+/', trim($value)) ?: [];
        $emails = [];
        $invalid = [];

        foreach ($tokens as $token) {
            $email = trim($token);
            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;
                continue;
            }

            $emails[strtolower($email)] = $email;
        }

        if ($invalid !== []) {
            throw new \RuntimeException('Gecersiz mail adresi: ' . implode(', ', array_slice($invalid, 0, 3)));
        }

        $emails = array_values($emails);
        if (count($emails) > self::MAX_TEST_RECIPIENTS) {
            throw new \RuntimeException('Tek testte en fazla ' . self::MAX_TEST_RECIPIENTS . ' mail adresi kullanilabilir.');
        }

        return $emails;
    }

    private function dataFromPost(): array
    {
        return [
            'type' => (string) ($_POST['type'] ?? 'mail'),
            'slug' => (string) ($_POST['slug'] ?? ''),
            'name' => (string) ($_POST['name'] ?? ''),
            'subject' => (string) ($_POST['subject'] ?? ''),
            'html' => (string) ($_POST['html'] ?? ''),
            'css' => (string) ($_POST['css'] ?? ''),
            'project_json' => (string) ($_POST['project_json'] ?? ''),
            'active' => !empty($_POST['active']) ? 1 : 0,
            'is_system' => !empty($_POST['is_system']) ? 1 : 0,
        ];
    }

    private function typeFromRequest(): string
    {
        $type = (string) ($_GET['type'] ?? 'mail');
        return in_array($type, ['mail', 'report'], true) ? $type : 'mail';
    }

    private function title(string $type): string
    {
        return $type === 'report' ? 'Rapor Sablonlari' : 'Mail Sablonlari';
    }
}
