<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class AuditLogger
{
    public static function log(string $action, ?string $details = null, ?string $actor = null): void
    {
        try {
            Database::pdo()->prepare('INSERT INTO audit_logs (actor, action, details, ip_address, created_at) VALUES (:actor, :action, :details, :ip, :created_at)')
                ->execute([
                    'actor' => $actor ?? ($_SESSION['user']['email'] ?? null),
                    'action' => $action,
                    'details' => $details,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable) {
            // Audit logging must never block the main workflow.
        }
    }
}

