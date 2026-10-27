<?php
// Endpoint penyimpanan hasil UX testing (dipanggil dari index.html)
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Metode tidak diizinkan.']);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    respond(400, ['ok' => false, 'error' => 'Format data tidak valid.']);
}

$submissionId = (string)($in['submission_id'] ?? '');
$name = trim((string)($in['participant_name'] ?? ''));
$role = trim((string)($in['participant_role'] ?? ''));
$device = trim((string)($in['device'] ?? ''));
$markdown = (string)($in['markdown'] ?? '');
$module = (string)($in['module'] ?? '');

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $submissionId)) {
    respond(422, ['ok' => false, 'error' => 'ID pengisian tidak valid.']);
}
if ($name === '' || strlen($name) > 150) {
    respond(422, ['ok' => false, 'error' => 'Nama partisipan wajib diisi (maks. 150 karakter).']);
}
if (strlen($role) > 100 || strlen($device) > 100) {
    respond(422, ['ok' => false, 'error' => 'Data peran/perangkat terlalu panjang.']);
}
if (!in_array($module, MODULES, true)) {
    respond(422, ['ok' => false, 'error' => 'Modul aplikasi tidak dikenal.']);
}
if ($markdown === '' || strlen($markdown) > 500000) {
    respond(422, ['ok' => false, 'error' => 'Isi laporan kosong atau terlalu besar.']);
}

try {
    // Setiap pengiriman = baris baru, data lama tidak pernah ditimpa.
    // submission_id yang sama hanya muncul saat request yang sama dikirim ulang (retry), jadi diabaikan.
    $stmt = db()->prepare(
        'INSERT IGNORE INTO ux_results (submission_id, participant_name, participant_role, device, module, filename, markdown, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        strtolower($submissionId),
        $name,
        $role,
        $device,
        $module,
        safe_filename((string)($in['filename'] ?? '')),
        $markdown,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('ux-testing submit: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Server gagal menyimpan data.']);
}

respond(200, ['ok' => true]);
