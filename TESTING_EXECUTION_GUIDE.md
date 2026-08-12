# TESTING EXECUTION GUIDE

Panduan praktis ini disusun agar pemilik proyek dapat menjalankan seluruh suite pengujian otomatis, memverifikasi validasi form pengaduan, mengukur kinerja kriptografi hybrid, serta membaca keluaran berkas CSV.

---

## 1. PRASYARAT LINGKUNGAN (ENVIRONMENT SETUP)

Sebelum menjalankan pengujian, pastikan spesifikasi lokal Anda memenuhi kriteria berikut (diambil dari `composer.json` proyek):
*   **PHP Version**: `^8.2`
*   **Laravel Framework**: `^12.0`
*   **PHPUnit**: `^11.5.3`
*   **Composer**: Dependensi terinstal lengkap.
*   **OpenSSL**: Ekstensi OpenSSL diaktifkan di `php.ini` (wajib untuk AES & RSA).

---

## 2. PERSIAPAN PROYEK (SETUP STEP)

1.  Buka terminal/command prompt di direktori utama `ppkpt`.
2.  Instal dependensi pengembangan menggunakan Composer:
    ```bash
    composer install
    ```
3.  Salin file konfigurasi lingkungan dan hasilkan kunci aplikasi Laravel:
    ```bash
    copy .env.example .env
    php artisan key:generate
    ```
4.  Hasilkan pasangan kunci asimetris RSA-2048 (.pem) untuk keperluan kriptografi hybrid:
    ```bash
    php artisan rsa:generate
    ```
    *Peringatan Keamanan: Jangan pernah membagikan berkas `private_key.pem` dan isi berkas `.env` kepada siapa pun atau meng-commit-nya ke git.*

---

## 3. MENJALANKAN WHITE BOX TEST

Jalankan pengujian unit terhadap logika matematika helper kriptografi:

### A. Uji Coba AES Helper
```bash
php artisan test tests/Unit/Kriptografi/AesHelperTest.php
```
*   *Deskripsi*: Memverifikasi enkripsi/dekripsi AES-256-CBC, IV handling, null/empty data, dan fallback hashing key dinamis.

### B. Uji Coba RSA Helper
```bash
php artisan test tests/Unit/Kriptografi/RsaHelperTest.php
```
*   *Deskripsi*: Memverifikasi pembacaan kunci PEM publik/privat, enkripsi kunci AES, dekripsi kunci AES, dan pembatasan data enkripsi asimetris.

---

## 4. MENJALANKAN VALIDATION TEST

Jalankan pengujian fitur untuk mensimulasikan input formulir pengaduan:
```bash
php artisan test --filter=AduanValidationFeatureTest
```
*   *Deskripsi*: Menjalankan 18 skenario validasi otomatis. Menguji format file valid (PDF, DOC, DOCX, JPG, PNG) dan format file tidak valid (TXT, ZIP), batas karakter nama pelapor (255), MIME spoofing,required fields, dan email.
*   *Output*: Hasil pengujian dicatat secara otomatis dan ditambahkan ke dalam berkas [`storage/app/testing/hasil_validasi.csv`](file:///d:/ppkpt/storage/app/testing/hasil_validasi.csv).

---

## 5. MENJALANKAN PERFORMANCE TEST

Untuk mengukur kecepatan dan ketahanan integritas biner hybrid kriptografi secara dinamis di terminal, Anda harus menentukan berkas uji fisik yang ada pada disk Anda.

### Perintah Eksekusi Performa (PowerShell Loop):
```powershell
$pdfFiles = @("uji/pdf_1.7mb.pdf", "uji/pdf_3.2mb.pdf", "uji/pdf_5mb.pdf", "uji/pdf_8mb.pdf", "uji/pdf_10.9mb.pdf"); $jpgFiles = @("uji/1.76.jpg", "uji/4.68.JPG", "uji/6.70.JPG", "uji/5.44.JPG", "uji/3.11.JPG"); foreach($f in $pdfFiles){ $env:TEST_FILE=$f; php artisan test --filter=test_hybrid_encryption_performance }; $env:TEST_CHAR_COUNT="100"; foreach($f in $jpgFiles){ $env:TEST_FILE=$f; php artisan test --filter=test_hybrid_encryption_performance }; foreach($mode in @("file_not_found", "invalid_key", "corrupt_aes_key", "corrupt_ciphertext", "hash_mismatch")){ $env:TEST_FILE="uji/pdf_1.7mb.pdf"; $env:TEST_MODE=$mode; php artisan test --filter=test_hybrid_encryption_performance }; Remove-Item env:TEST_FILE, env:TEST_CHAR_COUNT, env:TEST_MODE
```
*   *Output*: Hasil uji performa dan integritas (SHA-256 compare) dicatat otomatis ke berkas [`storage/app/testing/hasil_pengujian.csv`](file:///d:/ppkpt/storage/app/testing/hasil_pengujian.csv).

---

## 6. MENJALANKAN FULL TEST SUITE

Untuk memverifikasi seluruh suite pengujian otomatis proyek (Unit + Feature):
```bash
php artisan test
```
*   *Interpretasi Hasil*:
    *   **Passed**: Uji coba berhasil.
    *   **Skipped**: Skenario dilewati (misalnya: test dynamic manual yang membutuhkan variabel `$env:TEST_FILE`). Skipped test **bukan** merupakan indikasi test gagal.

### Baseline Hasil Pengujian Terakhir:
*   Total Tests: 73
*   Passed: 70
*   Skipped: 3
*   Failed / Errors: 0

---

## 7. STRUKTUR DAN LOKASI BERKAS CSV

Kedua berkas keluaran pengujian disimpan di folder `storage/app/testing/`:

1.  **`hasil_validasi.csv`** (17 Kolom):
    *   Mencatat hasil verifikasi form pengajuan.
    *   *Kolom Kunci*: `No`, `Nama Skenario`, `Expected Result`, `Actual Result`, `Submission Status`, `Status Akhir` (PASS/FAIL).
2.  **`hasil_pengujian.csv`** (15 Kolom):
    *   Mencatat waktu dan keutuhan enkripsi/dekripsi hybrid.
    *   *Kolom Kunci*: `Nama File`, `Waktu Enkripsi (s)`, `Waktu Dekripsi (s)`, `Hash Asli`, `Hash Hasil Dekripsi`, `Integrity Check` (PASS/FAIL).

---

## 8. KOSISTENSI WHITE BOX SECARA MANUAL

Untuk memverifikasi kebenaran White Box secara manual:
1.  Buka [`AesHelper.php`](file:///d:/ppkpt/app/Helpers/AesHelper.php) dan [`RsaHelper.php`](file:///d:/ppkpt/app/Helpers/RsaHelper.php).
2.  Identifikasi decision point ($D$) menggunakan aturan predicate-level (jumlah `if`, `elseif`, ternary).
3.  Hitung $V(G) = D + 1$.
4.  Gambarkan CFG sederhana, hitung $E - N + 2$.
5.  Petakan setiap jalur independen minimal ke method unit test di dalam `tests/Unit/`.
6.  Pastikan Path Coverage bernilai 100.00% (19 dari 19 jalur covered).

---

## 9. TROUBLESHOOTING (PANDUAN ERROR)

*   **Error: "RSA key not found"**:
    *   *Sebab*: Kunci PEM asimetris belum dibuat.
    *   *Solusi*: Jalankan `php artisan rsa:generate` di terminal.
*   **Error: "openssl_pkey_new failed"**:
    *   *Sebab*: Pustaka OpenSSL di Windows tidak dapat menemukan konfigurasi `openssl.cnf`.
    *   *Solusi*: Atur variabel lingkungan sistem: `OPENSSL_CONF=C:\xampp\php\extras\ssl\openssl.cnf` (sesuaikan dengan path instalasi PHP Anda).
*   **Error: "TEST_FILE not set"**:
    *   *Sebab*: Anda menjalankan manual validation/performance test langsung tanpa memasang parameter input.
    *   *Solusi*: Gunakan PowerShell loop command di atas untuk mengisi parameter otomatis.

---

## 10. CHECKLIST HANDOVER AKHIR

*   [ ] `composer install` berhasil tanpa konflik.
*   [ ] Perintah `php artisan rsa:generate` menghasilkan kunci publik/privat PEM.
*   [ ] PHPUnit Unit Test AES Helper berhasil PASS (18 tests).
*   [ ] PHPUnit Unit Test RSA Helper berhasil PASS (9 tests passed, 1 skipped).
*   [ ] Validation Feature Test berhasil PASS (18 tests passed, 1 skipped).
*   [ ] Performance Feature Test dapat dieksekusi via PowerShell.
*   [ ] File `hasil_validasi.csv` (17 kolom) dan `hasil_pengujian.csv` (15 kolom) terisi otomatis.
*   [ ] Integritas hash SHA-256 bernilai PASS 100%.
*   [ ] Verifikasi 19 basis path White Box selesai.
