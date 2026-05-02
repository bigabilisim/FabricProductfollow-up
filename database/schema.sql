CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    company_code VARCHAR(16) NOT NULL,
    country_code VARCHAR(8) NOT NULL,
    production_year SMALLINT UNSIGNED NOT NULL,
    machine_no INT UNSIGNED NOT NULL,
    serial_number VARCHAR(120) NOT NULL,
    installed_at DATE NOT NULL,
    maintenance_period_days INT UNSIGNED NOT NULL DEFAULT 180,
    notify_before_days VARCHAR(80) NOT NULL DEFAULT '30,14,7,3,1',
    responsible_emails TEXT NOT NULL,
    hazard_note TEXT NULL,
    notes TEXT NULL,
    qr_token CHAR(64) NOT NULL UNIQUE,
    last_maintenance_at DATE NULL,
    next_maintenance_at DATE NOT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_devices_next_maintenance_at (next_maintenance_at),
    INDEX idx_devices_qr_token (qr_token),
    INDEX idx_devices_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_access_codes_device_email (device_id, email),
    CONSTRAINT fk_access_codes_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL,
    due_at DATE NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    response_token CHAR(64) NOT NULL UNIQUE,
    response_expires_at DATETIME NOT NULL,
    responded_at DATETIME NULL,
    rescheduled_at DATE NULL,
    read_ack_token CHAR(64) NULL UNIQUE,
    read_ack_at DATETIME NULL,
    hazard_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_maintenance_due_status (due_at, status),
    INDEX idx_maintenance_token (response_token),
    CONSTRAINT fk_maintenance_events_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NULL,
    maintenance_event_id INT UNSIGNED NULL,
    channel VARCHAR(40) NOT NULL,
    notification_type VARCHAR(80) NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    message_hash CHAR(64) NOT NULL,
    sent_at DATETIME NOT NULL,
    UNIQUE KEY uniq_notification_once (notification_type, recipient, message_hash),
    INDEX idx_notification_device (device_id),
    CONSTRAINT fk_notification_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_event FOREIGN KEY (maintenance_event_id) REFERENCES maintenance_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    mailed_at DATETIME NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor VARCHAR(190) NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(64) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
