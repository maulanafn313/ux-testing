# UX Testing Ekosistem Sorgum

Formulir usability testing (satu pertanyaan per layar) untuk 4 aplikasi Sorgum Living Labs. Hasil dikirim per modul ke MySQL sebagai laporan Markdown, lalu dilihat dan diunduh di halaman admin.

- `index.html` — formulir responden
- `submit.php` — endpoint penyimpanan hasil
- `admin.php` — daftar hasil, filter per modul, pratinjau & unduh `.md`

## Setup

1. Salin `config.example.php` menjadi `config.php`, lalu isi `DB_*`.
2. Buat hash password admin dan isi `ADMIN_PASSWORD_HASH`:
   ```
   php -r "echo password_hash('PasswordBaru', PASSWORD_DEFAULT);"
   ```
3. Buat database `ux_testing`. Tabel `ux_results` dibuat otomatis saat pertama dipakai.

Butuh PHP 8+ dan MySQL 8. Gunakan HTTPS saat hosting.
