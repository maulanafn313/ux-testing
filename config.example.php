<?php
// Konfigurasi aplikasi UX Testing — sesuaikan saat hosting.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'ux_testing';
const DB_USER = 'root';
const DB_PASS = '';

// Hash password admin. Buat hash baru dengan:
//   php -r "echo password_hash('PasswordBaru', PASSWORD_DEFAULT);"
const ADMIN_PASSWORD_HASH = 'GANTI_DENGAN_HASH_PASSWORD';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Tabel dibuat otomatis saat pertama kali dipakai
    $pdo->exec("CREATE TABLE IF NOT EXISTS ux_results (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        submission_id CHAR(36) NOT NULL UNIQUE,
        participant_name VARCHAR(150) NOT NULL,
        participant_role VARCHAR(100) NULL,
        device VARCHAR(100) NULL,
        module VARCHAR(100) NULL,
        filename VARCHAR(200) NOT NULL,
        markdown MEDIUMTEXT NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_module (module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Tabel lama (sebelum ada pengiriman per modul): tambah kolom module
    if (!$pdo->query("SHOW COLUMNS FROM ux_results LIKE 'module'")->fetch()) {
        $pdo->exec('ALTER TABLE ux_results ADD COLUMN module VARCHAR(100) NULL AFTER device, ADD INDEX idx_module (module)');
    }

    return $pdo;
}

// Modul yang dikirim terpisah dari formulir (harus sama dengan nama di index.html)
const MODULES = ['Dashboard IoT', 'E-Catalog Sorgum', 'SCM Sorgum', 'Community Sorgum', 'Evaluasi Ekosistem'];

function safe_filename(string $name): string
{
    $name = preg_replace('/[^a-z0-9.-]/', '', strtolower($name));
    return preg_match('/^[a-z0-9-]+\.md$/', $name) ? $name : 'hasil-responden.md';
}
