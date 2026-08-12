# PANDUAN EKSEKUSI PENGUJIAN OTOMATIS

Panduan praktis ini disusun agar pemilik proyek dapat menjalankan secara mandiri seluruh skenario pengujian otomatis, validasi form, pengukuran performa, serta verifikasi hasil keluaran berkas CSV.

---

## A. PERSYARATAN LINGKUNGAN (PREREQUISITES)

Pastikan lingkungan server lokal Anda memenuhi spesifikasi minimum berikut (diambil dari berkas `composer.json` proyek):
*   **PHP Version**: `^8.2`
*   **Laravel Framework**: `^12.0`
*   **PHPUnit**: `^11.5.3`
*   **Composer**: Dependensi terinstal lengkap.
*   **OpenSSL**: Ekstensi OpenSSL PHP diaktifkan pada berkas `php.ini`.

---

## B. SETUP PROYEK (SETUP STEP)

1.  Buka terminal/command prompt di direktori utama `ppkpt` Anda.
2.  Instal dependensi library php menggunakan Composer:
    ```bash
    composer install
    ```
3.  Salin konfigurasi env dan hasilkan kunci aplikasi Laravel:
    ```bash
    copy .env.example .env
    php artisan key:generate
    ```
4.  Hasilkan pasangan kunci asimetris RSA-2048 (.pem) lokal untuk enkripsi kunci AES:
    ```bash
    php artisan rsa:generate
    ```
    *Peringatan Keamanan: Jangan pernah membagikan berkas `private_key.pem` dan isi berkas `.env` kepada siapa pun atau meng-commit-nya ke repositori git.*

---

## C. MENJALANKAN WHITE BOX TEST

Jalankan pengujian unit terhadap logika matematika helper kriptografi menggunakan command berikut:

### 1. Uji Coba AES Helper
```bash
php artisan test tests/Unit/Kriptografi/AesHelperTest.php
```
*   *Aspek Teruji*: Keabsahan generator key, enkripsi/dekripsi AES-256-CBC dinamis dan statis, error handling IV, dan fallback hashing key.

### 2. Uji Coba RSA Helper
```bash
php artisan test tests/Unit/Kriptografi/RsaHelperTest.php
```
*   *Aspek Teruji*: Pembacaan PEM keys, enkripsi kunci AES, dekripsi kunci AES, missing-key exceptions, dan pemblokiran enkripsi data di atas 245 byte.

---

## D. MENJALANKAN VALIDATION TEST

Jalankan pengujian fitur formulir pengaduan Black Box dengan command berikut:
```bash
php artisan test --filter=AduanValidationFeatureTest
```
*   *Deskripsi*: Mensimulasikan 18 skenario pengiriman berkas. Menguji tipe berkas valid (PDF, DOC, DOCX, JPG, PNG) dan tidak valid (TXT, ZIP), batas 2048 KB berkas, manipulasi biner asli (MIME spoofing), dan field wajib.
*   *Output*: Hasil uji dicatat otomatis ke berkas [`storage/app/testing/hasil_validasi.csv`](file:///d:/ppkpt/storage/app/testing/hasil_validasi.csv).

---

## E. MENJALANKAN PERFORMANCE & INTEGRITY TEST

Pengujian performa dinamis membutuhkan folder uji fisik berisi data contoh (5 PDF dan 5 JPG). Folder ini diletakkan pada root proyek: `D:\ppkpt\uji\`.

### Perintah Eksekusi Otomatis (PowerShell Loop):
```powershell
$pdfFiles = @("uji/pdf_1.7mb.pdf", "uji/pdf_3.2mb.pdf", "uji/pdf_5mb.pdf", "uji/pdf_8mb.pdf", "uji/pdf_10.9mb.pdf"); $jpgFiles = @("uji/1.76.jpg", "uji/4.68.JPG", "uji/6.70.JPG", "uji/5.44.JPG", "uji/3.11.JPG"); foreach($f in $pdfFiles){ $env:TEST_FILE=$f; php artisan test --filter=test_hybrid_encryption_performance }; $env:TEST_CHAR_COUNT="100"; foreach($f in $jpgFiles){ $env:TEST_FILE=$f; php artisan test --filter=test_hybrid_encryption_performance }; foreach($mode in @("file_not_found", "invalid_key", "corrupt_aes_key", "corrupt_ciphertext", "hash_mismatch")){ $env:TEST_FILE="uji/pdf_1.7mb.pdf"; $env:TEST_MODE=$mode; php artisan test --filter=test_hybrid_encryption_performance }; Remove-Item env:TEST_FILE, env:TEST_CHAR_COUNT, env:TEST_MODE
```
*   *Mekanisme*: Pengujian akan membaca relative path `uji/...` lalu menyelaraskannya dengan root proyek melalui fungsi `base_path()`. Skenario kegagalan (file hilang, cipher rusak) akan menguji respon ketahanan sistem secara riil.
*   *Output*: Hasil performa dicatat otomatis ke berkas [`storage/app/testing/hasil_pengujian.csv`](file:///d:/ppkpt/storage/app/testing/hasil_pengujian.csv).

---

## F. MENJALANKAN SELURUH TEST SUITE

Gunakan command berikut untuk memverifikasi keseluruhan suite pengujian (Unit & Feature Test):
```bash
php artisan test
```
*   *Membaca Output*:
    *   **Passed**: Pengujian lolos (berhasil).
    *   **Skipped**: Skenario dilewati karena membutuhkan environment variable dinamis.
    *   **Failed / Errors**: Terjadi kesalahan program (harus diperbaiki).

### Baseline Hasil Terakhir Proyek:
*   Total Tests: 73
*   Passed: 70
*   Skipped: 3
*   Failed / Errors: 0

---

## G. VERIFIKASI LOG CSV SECARA fungsional

1.  **`hasil_validasi.csv`**:
    *   `Expected Result` bernilai `ACCEPT` + `Actual Result` `ACCEPT` $\rightarrow$ Status Akhir: `PASS`.
    *   `Expected Result` bernilai `REJECT` + `Actual Result` `REJECT` $\rightarrow$ Status Akhir: `PASS`.
    *   *Catatan*: Penolakan berkas terlarang adalah keberhasilan validasi (bukan test gagal).
2.  **`hasil_pengujian.csv`**:
    *   Memverifikasi kesamaan `Hash Asli` dengan `Hash Hasil Dekripsi`. Jika bernilai identik, status `Integrity Check` bernilai `PASS`.
    *   Mencatat waktu enkripsi dan dekripsi hybrid secara riil dalam satuan detik (s).

---

## H. VERIFIKASI WHITE BOX SECARA MANUAL
1.  Buka helper [`AesHelper.php`](file:///d:/ppkpt/app/Helpers/AesHelper.php) dan [`RsaHelper.php`](file:///d:/ppkpt/app/Helpers/RsaHelper.php).
2.  Hitung jumlah decision point $D$ (jumlah if, elseif, ternary).
3.  Hitung $V(G) = D + 1$.
4.  Identifikasi basis path minimal dan pasangkan dengan test method terkait.
5.  *Baseline*: 12 Decision, 19 Independent Path, 19 Covered (100.00% Path Coverage).

---

## I. TROUBLESHOOTING

*   **Error: "Private key not found"**:
    *   *Penyebab*: Kunci PEM belum dibuat lokal.
    *   *Solusi*: Jalankan perintah `php artisan rsa:generate`.
*   **Error: "openssl_pkey_new failed"**:
    *   *Penyebab*: Ketiadaan berkas `openssl.cnf` pada OS Windows.
    *   *Solusi*: Definisikan path absolut SSL pada environment sistem Windows Anda, misalnya: `OPENSSL_CONF=C:\xampp\php\extras\ssl\openssl.cnf`.
*   **Error: "TEST_FILE tidak diatur"**:
    *   *Penyebab*: Eksekusi test performa manual dijalankan tanpa memasang parameter input.
    *   *Solusi*: Jalankan script PowerShell loop yang tertera pada bagian E.

---

## J. SECURITY WARNING
*   **Dilarang Keras** menyebarkan berkas `.env`, PEM private key, password database, dan kredensial developer.
*   Pemilik proyek wajib membangkitkan kunci RSA mandiri di lingkungan produksinya sendiri.

---

## K. CHECKLIST MENJALANKAN PENGUJIAN

*   [ ] `composer install` berhasil tanpa kesalahan.
*   [ ] Perintah `php artisan rsa:generate` menghasilkan public & private keys.
*   [ ] PHPUnit Unit Test AES Helper berhasil PASS (18 tests).
*   [ ] PHPUnit Unit Test RSA Helper berhasil PASS (9 tests passed, 1 skipped).
*   [ ] Validation Feature Test berhasil PASS (18 tests passed, 1 skipped).
*   [ ] Performance Feature Test dapat dijalankan.
*   [ ] `php artisan test` berhasil dieksekusi penuh.
*   [ ] Berkas `hasil_validasi.csv` terisi otomatis.
*   [ ] Berkas `hasil_pengujian.csv` terisi otomatis.
*   [ ] Hasil integritas SHA-256 terbukti cocok (PASS).
