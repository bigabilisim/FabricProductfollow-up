<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\AuditLogger;

final class AuthController
{
    public function showLogin(): void
    {
        if (current_user()) {
            redirect('/admin');
        }

        view('auth/login', ['title' => 'Admin Giris']);
    }

    public function login(): void
    {
        verify_csrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $statement = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            flash('E-posta veya parola hatali.', 'error');
            redirect('/login');
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];

        AuditLogger::log('auth.login');
        redirect('/admin');
    }

    public function logout(): void
    {
        verify_csrf();
        AuditLogger::log('auth.logout');
        unset($_SESSION['user']);
        redirect('/login');
    }
}

