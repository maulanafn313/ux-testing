<?php
// Halaman admin: lihat & unduh hasil UX testing yang tersimpan di MySQL
require __DIR__ . '/config.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sesi kedaluwarsa. Muat ulang halaman.');
    }
}

function avg_seq(string $markdown): string
{
    return preg_match('/Rata-rata Skor Kemudahan \(SEQ\):\*\* \*\*([\d.]+) \/ 7/', $markdown, $m) ? $m[1] : '-';
}

function module_label(?string $module): string
{
    return $module ?: 'Laporan lengkap';
}

function fmt_date(string $datetime): string
{
    $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($datetime);
    return $t ? date('j', $t) . ' ' . $months[(int)date('n', $t)] . ' ' . date('Y, H.i', $t) : $datetime;
}

function page(string $title, string $body): void
{
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#1C4D31">
  <title><?= h($title) ?> - Admin UX Testing Sorgum</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌾</text></svg>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.min.css">
  <style>
    :root {
      --leaf: #1C4D31; --leaf-50: #EEF5F0; --grain: #D9A53B; --grain-50: #FBF3E1; --grain-600: #A8761F;
      --mist: #F3F5EF; --ink: #17251C; --line: #DDE3D8; --muted: #5D6B61;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: var(--mist); color: var(--ink); font-size: 14px; -webkit-font-smoothing: antialiased; }
    a { color: inherit; }
    button:focus-visible, a:focus-visible, input:focus-visible { outline: 3px solid var(--grain); outline-offset: 2px; }

    .topbar { background: linear-gradient(160deg, #1C4D31 0%, #143724 80%); color: #fff; position: relative; overflow: hidden; }
    .topbar::after {
      content: ''; position: absolute; right: -30px; top: -40px; width: 180px; height: 180px; opacity: .4;
      background: radial-gradient(circle, rgba(217, 165, 59, .8) 2px, transparent 2.6px) 0 0 / 13px 13px;
      -webkit-mask-image: radial-gradient(circle, #000 25%, transparent 70%); mask-image: radial-gradient(circle, #000 25%, transparent 70%);
      pointer-events: none;
    }
    .topbar-inner { max-width: 1100px; margin: 0 auto; padding: 18px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; position: relative; z-index: 1; }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand-mark { width: 40px; height: 40px; border-radius: 14px; background: rgba(255, 255, 255, .12); display: grid; place-items: center; font-size: 20px; }
    .brand h1 { font-size: 17px; font-weight: 800; margin: 0; }
    .brand p { margin: 2px 0 0; font-size: 12px; color: rgba(255, 255, 255, .7); }

    main { max-width: 1100px; margin: 0 auto; padding: 20px 16px 48px; }
    .card { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 18px; margin-bottom: 16px; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 14px; border-radius: 12px; border: 1px solid var(--line); background: #fff; color: var(--ink); font-family: inherit; font-size: 13px; font-weight: 600; line-height: 1; text-decoration: none; cursor: pointer; transition: background .15s; white-space: nowrap; }
    .btn:hover { background: var(--leaf-50); }
    .btn-primary { background: var(--leaf); border-color: var(--leaf); color: #fff; }
    .btn-primary:hover { background: #143724; }
    .btn-ghost { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .25); color: #fff; }
    .btn-ghost:hover { background: rgba(255, 255, 255, .2); }

    input[type=text], input[type=password] { width: 100%; height: 46px; padding: 0 14px; border: 1px solid var(--line); border-radius: 14px; font-family: inherit; font-size: 16px; background: #fff; color: var(--ink); }
    input:focus { outline: none; border-color: var(--leaf); box-shadow: 0 0 0 3px rgba(28, 77, 49, .15); }

    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (max-width: 560px) { .stat:last-child { grid-column: 1 / -1; } }
    .stat { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 16px 18px; }
    .stat span { display: block; font-size: 13px; color: var(--muted); }
    .stat b { display: block; font-size: 26px; font-weight: 800; margin-top: 4px; }
    .stat.grain { background: var(--grain-50); border-color: #F0DDAE; }
    .stat.grain b { color: var(--grain-600); }

    .search { display: flex; gap: 8px; flex-wrap: wrap; }
    .search input { flex: 1; min-width: 200px; }

    .list { display: grid; gap: 10px; }
    .row { display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: center; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 14px 16px; }
    .row-main { min-width: 0; display: flex; gap: 14px; align-items: center; }
    .seq-badge { flex-shrink: 0; width: 56px; height: 56px; border-radius: 16px; background: var(--grain-50); color: var(--grain-600); display: grid; place-items: center; text-align: center; line-height: 1; }
    .seq-badge b { font-size: 17px; font-weight: 800; }
    .seq-badge small { display: block; font-size: 10px; margin-top: 2px; }
    .row-name { font-weight: 700; font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .row-meta { color: var(--muted); font-size: 12.5px; margin-top: 3px; }
    .tag { display: inline-block; padding: 2px 8px; border-radius: 999px; background: var(--leaf-50); color: var(--leaf); font-size: 11.5px; font-weight: 600; margin: 4px 4px 0 0; }
    .row-actions { display: flex; gap: 6px; }
    .tag-module { background: var(--grain-50); color: #7A5410; }
    .row-file { overflow-wrap: anywhere; }
    .filters { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .filters a { padding: 7px 12px; border-radius: 999px; border: 1px solid var(--line); background: #fff; text-decoration: none; font-size: 13px; font-weight: 600; color: var(--muted); }
    .filters a[aria-current] { background: var(--leaf); border-color: var(--leaf); color: #fff; }
    .md-modal { text-align: left; max-height: 60vh; }
    @media (max-width: 560px) {
      .row { grid-template-columns: 1fr; }
      .row-actions .btn { flex: 1; }
    }

    .empty { text-align: center; padding: 40px 16px; color: var(--muted); }
    .empty b { display: block; color: var(--ink); font-size: 16px; margin-bottom: 4px; }

    .detail-head { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    pre { margin: 0; background: #0F1A13; color: #E5EDE7; padding: 18px; border-radius: 20px; overflow: auto; white-space: pre-wrap; word-break: break-word; font: 12.5px/1.65 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .muted { color: var(--muted); font-size: 13px; }
    .error { background: #FDECEA; border: 1px solid #F6C8C2; color: #8C1D13; padding: 10px 14px; border-radius: 12px; margin-bottom: 14px; font-size: 13px; }

    .login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 16px; background: #1B2A20 radial-gradient(1000px 520px at 50% -10%, #2E4B37 0%, transparent 65%) no-repeat; }
    .login { width: 100%; max-width: 380px; background: #fff; border-radius: 28px; padding: 28px 24px; }
    .login .brand-mark { background: var(--leaf); margin-bottom: 16px; }
    .login h1 { margin: 0; font-size: 20px; font-weight: 800; }
    .login label { display: block; font-size: 13px; font-weight: 600; margin: 20px 0 8px; }
    .login .btn { width: 100%; height: 46px; margin-top: 16px; font-size: 14px; }

    .swal2-popup { font-family: inherit; border-radius: 28px; color: var(--ink); }
    .swal2-title { font-size: 1.2rem; font-weight: 800; color: var(--ink); }
    .swal2-html-container { color: var(--muted); }
    .swal2-styled { border-radius: 14px !important; font-weight: 700 !important; }
    .swal2-styled.swal-btn-muted { color: var(--ink) !important; }
    .swal2-icon.swal2-question { border-color: #ECCB80; color: var(--grain-600); }
  </style>
</head>
<body>
<?= $body ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.min.js"></script>
<script>
  // Pratinjau .md: ambil teks lalu tampilkan di modal (tanpa JS tetap bisa ke halaman detail)
  document.querySelectorAll('a[data-preview]').forEach(link => {
    link.addEventListener('click', async e => {
      e.preventDefault();
      const res = await fetch(link.dataset.preview, { credentials: 'same-origin' }).catch(() => null);
      if (!res || !res.ok) { location.href = link.href; return; }
      const pre = document.createElement('pre');
      pre.className = 'md-modal';
      pre.textContent = await res.text();
      const r = await Swal.fire({
        title: link.dataset.file,
        html: pre,
        width: 760,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Unduh .md',
        cancelButtonText: 'Tutup',
        confirmButtonColor: '#1C4D31',
        cancelButtonColor: '#E7ECE4',
        customClass: { cancelButton: 'swal-btn-muted' }
      });
      if (r.isConfirmed) location.href = link.dataset.download;
    });
  });

  // Konfirmasi keluar
  document.querySelectorAll('form[data-confirm-logout]').forEach(form => {
    form.addEventListener('submit', e => {
      if (form.dataset.confirmed) return;
      e.preventDefault();
      Swal.fire({
        icon: 'question',
        title: 'Keluar dari halaman admin?',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Keluar',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#1C4D31',
        cancelButtonColor: '#E7ECE4',
        customClass: { cancelButton: 'swal-btn-muted' }
      }).then(r => {
        if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); }
      });
    });
  });
</script>
</body>
</html>
<?php
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = h($_SESSION['csrf']);
$action = $_GET['action'] ?? '';

// ---------- Login / Logout ----------
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    check_csrf();
    if (password_verify((string)$_POST['password'], ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    }
    sleep(1); // ponytail: perlambat brute force sederhana, pakai rate limit per-IP kalau endpoint diserang
    $loginError = 'Password salah. Periksa lalu coba lagi.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    check_csrf();
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['admin'])) {
    ob_start(); ?>
  <div class="login-wrap">
    <div class="login">
      <div class="brand-mark" aria-hidden="true">🌾</div>
      <h1>Admin UX Testing</h1>
      <p class="muted" style="margin:4px 0 0">Masuk untuk melihat hasil pengujian Ekosistem Sorgum.</p>
      <form method="post" action="admin.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <label for="password">Password</label>
        <?php if ($loginError): ?><div class="error"><?= h($loginError) ?></div><?php endif; ?>
        <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
        <button type="submit" class="btn btn-primary">Masuk</button>
      </form>
    </div>
  </div>
<?php
    page('Masuk', ob_get_clean());
    exit;
}

// ---------- Halaman terautentikasi ----------
$headerHtml = '<header class="topbar"><div class="topbar-inner">'
    . '<a href="admin.php" class="brand" style="text-decoration:none"><div class="brand-mark" aria-hidden="true">🌾</div>'
    . '<div><h1>Hasil UX Testing</h1><p>Ekosistem Digital Sorgum</p></div></a>'
    . '<form method="post" action="admin.php?action=logout" data-confirm-logout><input type="hidden" name="csrf" value="' . $csrf . '">'
    . '<button type="submit" class="btn btn-ghost">Keluar</button></form>'
    . '</div></header>';

try {
    $pdo = db();

    if ($action === 'download' || $action === 'view' || $action === 'raw') {
        $stmt = $pdo->prepare('SELECT * FROM ux_results WHERE id = ?');
        $stmt->execute([(int)($_GET['id'] ?? 0)]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            page('Tidak ditemukan', $headerHtml . '<main><div class="card empty"><b>Data tidak ditemukan</b>Mungkin sudah dihapus. <a href="admin.php">Kembali ke daftar</a></div></main>');
            exit;
        }

        if ($action === 'raw') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $row['markdown'];
            exit;
        }

        if ($action === 'download') {
            $filename = safe_filename($row['filename']);
            header('Content-Type: text/markdown; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($row['markdown']));
            echo $row['markdown'];
            exit;
        }

        ob_start(); ?>
  <main>
    <div class="detail-head">
      <a class="btn" href="admin.php">&larr; Semua hasil</a>
      <a class="btn btn-primary" href="admin.php?action=download&amp;id=<?= (int)$row['id'] ?>">Unduh <?= h(safe_filename($row['filename'])) ?></a>
    </div>
    <div class="card">
      <div class="row-main">
        <div class="seq-badge"><div><b><?= h(avg_seq($row['markdown'])) ?></b><small>SEQ / 7</small></div></div>
        <div style="min-width:0">
          <div class="row-name"><?= h($row['participant_name']) ?></div>
          <div><span class="tag tag-module"><?= h(module_label($row['module'])) ?></span><span class="tag"><?= h($row['participant_role']) ?></span><span class="tag"><?= h($row['device']) ?></span></div>
          <div class="row-meta row-file"><?= h(safe_filename($row['filename'])) ?></div>
          <div class="row-meta">Dikirim <?= h(fmt_date($row['created_at'])) ?><?php if ($row['updated_at'] !== $row['created_at']): ?>, diperbarui <?= h(fmt_date($row['updated_at'])) ?><?php endif; ?></div>
        </div>
      </div>
    </div>
    <pre><?= h($row['markdown']) ?></pre>
  </main>
<?php
        page($row['participant_name'], $headerHtml . ob_get_clean());
        exit;
    }

    // Daftar hasil
    $q = trim((string)($_GET['q'] ?? ''));
    $module = (string)($_GET['module'] ?? '');
    if (!in_array($module, MODULES, true)) $module = '';
    // ponytail: tanpa paginasi, tambahkan LIMIT/OFFSET kalau responden sudah ratusan
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = 'participant_name LIKE ?';
        $params[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if ($module !== '') {
        $where[] = 'module = ?';
        $params[] = $module;
    }
    $stmt = $pdo->prepare('SELECT id, participant_name, participant_role, device, module, filename, markdown, created_at, updated_at FROM ux_results'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC, id DESC');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $totalAll = (int)$pdo->query('SELECT COUNT(*) FROM ux_results')->fetchColumn();
} catch (Throwable $e) {
    error_log('ux-testing admin: ' . $e->getMessage());
    http_response_code(500);
    page('Error', $headerHtml . '<main><div class="error">Gagal terhubung ke database. Periksa pengaturan DB_* di config.php.</div></main>');
    exit;
}

$seqValues = array_filter(array_map(fn($r) => avg_seq($r['markdown']), $rows), 'is_numeric');
$overallSeq = $seqValues ? number_format(array_sum($seqValues) / count($seqValues), 2) : '-';
$latest = $rows ? fmt_date($rows[0]['created_at']) : '-';
$filtered = $q !== '' || $module !== '';
$filterUrl = fn(string $m) => 'admin.php?' . http_build_query(array_filter(['q' => $q, 'module' => $m]));

ob_start(); ?>
  <main>
    <div class="stats">
      <div class="stat"><span>Pengiriman<?= $filtered ? ' (tersaring)' : '' ?></span><b><?= count($rows) ?><?php if ($filtered): ?><small class="muted"> / <?= $totalAll ?></small><?php endif; ?></b></div>
      <div class="stat grain"><span>Rata-rata SEQ</span><b><?= h($overallSeq) ?> <small class="muted">/ 7</small></b></div>
      <div class="stat"><span>Pengiriman terakhir</span><b style="font-size:16px;margin-top:10px"><?= h($latest) ?></b></div>
    </div>

    <div class="card">
      <form method="get" action="admin.php" class="search">
        <?php if ($module !== ''): ?><input type="hidden" name="module" value="<?= h($module) ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Cari nama partisipan" aria-label="Cari nama partisipan">
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if ($filtered): ?><a class="btn" href="admin.php">Tampilkan semua</a><?php endif; ?>
      </form>
      <nav class="filters" aria-label="Saring per modul">
        <a href="<?= h($filterUrl('')) ?>"<?= $module === '' ? ' aria-current="page"' : '' ?>>Semua modul</a>
        <?php foreach (MODULES as $m): ?>
          <a href="<?= h($filterUrl($m)) ?>"<?= $module === $m ? ' aria-current="page"' : '' ?>><?= h($m) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <?php if (!$rows): ?>
      <div class="card empty">
        <?php if ($filtered): ?>
          <b>Tidak ada hasil untuk saringan ini</b>Coba kata kunci atau modul lain, atau tampilkan semua hasil.
        <?php else: ?>
          <b>Belum ada hasil</b>Hasil muncul di sini setiap kali responden mengirim satu modul di formulir.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($rows as $r): ?>
          <div class="row">
            <div class="row-main">
              <div class="seq-badge" title="Rata-rata skor SEQ"><div><b><?= h(avg_seq($r['markdown'])) ?></b><small>SEQ / 7</small></div></div>
              <div style="min-width:0">
                <div class="row-name"><?= h($r['participant_name']) ?></div>
                <div><span class="tag tag-module"><?= h(module_label($r['module'])) ?></span><span class="tag"><?= h($r['participant_role']) ?></span><span class="tag"><?= h($r['device']) ?></span></div>
                <div class="row-meta row-file"><?= h(safe_filename($r['filename'])) ?></div>
                <div class="row-meta">Dikirim <?= h(fmt_date($r['created_at'])) ?><?php if ($r['updated_at'] !== $r['created_at']): ?>, diperbarui <?= h(fmt_date($r['updated_at'])) ?><?php endif; ?></div>
              </div>
            </div>
            <div class="row-actions">
              <a class="btn" href="admin.php?action=view&amp;id=<?= (int)$r['id'] ?>" data-preview="admin.php?action=raw&amp;id=<?= (int)$r['id'] ?>" data-download="admin.php?action=download&amp;id=<?= (int)$r['id'] ?>" data-file="<?= h(safe_filename($r['filename'])) ?>">Preview .md</a>
              <a class="btn btn-primary" href="admin.php?action=download&amp;id=<?= (int)$r['id'] ?>">Unduh .md</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
<?php
page('Daftar Hasil', $headerHtml . ob_get_clean());
