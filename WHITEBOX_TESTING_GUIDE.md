# WHITE BOX TESTING GUIDE (BASIS PATH TESTING)

Dokumen panduan ini menjelaskan secara terperinci tentang metodologi, objek pengujian, formula, kontrol graf, dan hasil pengujian basis path (*White Box Testing*) yang diimplementasikan pada proyek Laravel ini.

---

## 1. TUJUAN WHITE BOX TESTING

Pengujian White Box pada proyek ini difokuskan pada helper kriptografi menggunakan metode **Basis Path Testing**. 
*   **Fokus Utama**: Menganalisis struktur logika internal dan jalur eksekusi di dalam kode sumber, bukan sekadar memverifikasi kebenaran keluaran (output) aplikasi.
*   **Aspek yang Dianalisis**:
    *   *Control Flow*: Aliran eksekusi program dari baris ke baris.
    *   *Decision Point*: Titik percabangan logika di mana arah eksekusi ditentukan.
    *   *Cyclomatic Complexity*: Ukuran kuantitatif kompleksitas logika program.
    *   *Independent Path*: Jalur independen minimal yang harus dieksekusi setidaknya satu kali.
    *   *Path Coverage*: Metrik cakupan jalur untuk memastikan seluruh basis path teruji.

---

## 2. OBJEK PENGUJIAN

Pengujian dilakukan terhadap 7 fungsi inti kriptografi hybrid (AES-256 + RSA-2048):

### A. [`AesHelper.php`](file:///d:/ppkpt/app/Helpers/AesHelper.php)
1.  `generateKey()`: Menghasilkan kunci simetris 256-bit (32 karakter heksadesimal) acak secara aman.
2.  `encryptWithKey($plain, $key)`: Mengenkripsi plain-text menggunakan kunci dinamis dan inisialisasi IV 16 byte acak.
3.  `decryptWithKey($encrypted, $key)`: Mendekripsi cipher-text dinamis setelah memisahkan biner IV.
4.  `encrypt($plain)`: Mengenkripsi plain-text menggunakan kunci statis default aplikasi.
5.  `decrypt($encrypted, $key = null)`: Mendekripsi cipher-text secara umum (mendukung parameter kunci dinamis maupun fallback static/hashed key).

### B. [`RsaHelper.php`](file:///d:/ppkpt/app/Helpers/RsaHelper.php)
6.  `encryptKey($aesKey)`: Mengenkripsi kunci AES menggunakan kunci publik RSA-2048 (.pem).
7.  `decryptKey($encryptedAesKeyBase64)`: Mendekripsi kunci AES menggunakan kunci privat RSA-2048 (.pem).

---

## 3. KONVENSI CYCLOMATIC COMPLEXITY

Kompleksitas logika dihitung menggunakan **Predicate-Level McCabe (Conventional McCabe)** secara konsisten:
*   Setiap `if` dihitung sebagai 1 decision point ($D$).
*   Setiap `elseif` dihitung sebagai 1 decision point ($D$).
*   Setiap ternary `? :` dihitung sebagai 1 decision point ($D$).
*   Operator logika `&&` dan `||` di dalam satu kondisi `if` **tidak** dihitung sebagai decision terpisah.

### Formula Matematis:
$$V(G) = D + 1$$
$$V(G) = E - N + 2$$

*   $D$ = Jumlah Decision Point.
*   $E$ = Jumlah Edge (jalur transfer kontrol antar node pada graf).
*   $N$ = Jumlah Node (instruksi/pernyataan sekuensial pada graf).
*   $V(G)$ = Nilai Kompleksitas Siklomatis (Cyclomatic Complexity).

---

## 4. TABEL CYCLOMATIC COMPLEXITY FINAL

Berdasarkan struktur kode sumber aktual, berikut adalah nilai kompleksitas siklomatis terverifikasi:

| Function | Decision D | V(G) | E | N | E-N+2 | Independent Path | Status |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| `generateKey()` | 0 | 1 | 1 | 2 | 1 | 1 | ✅ SINKRON |
| `encryptWithKey()` | 1 | 2 | 5 | 5 | 2 | 2 | ✅ SINKRON |
| `decryptWithKey()` | 2 | 3 | 8 | 7 | 3 | 3 | ✅ SINKRON |
| `encrypt()` | 1 | 2 | 5 | 5 | 2 | 2 | ✅ SINKRON |
| `decrypt()` | 4 | 5 | 14 | 11 | 5 | 5 | ✅ SINKRON |
| `encryptKey()` | 2 | 3 | 8 | 7 | 3 | 3 | ✅ SINKRON |
| `decryptKey()` | 2 | 3 | 8 | 7 | 3 | 3 | ✅ SINKRON |
| **TOTAL V(G)** | **12** | **19** | - | - | - | **19** | **✅ TERVERIFIKASI** |

*Catatan: Nilai total V(G) = 19 merupakan akumulasi Cyclomatic Complexity dari seluruh 7 fungsi terpisah, bukan kompleksitas satu fungsi tunggal.*

---

## 5. DETAIL PEMETAAN JALUR INDEPENDEN (INDEPENDENT PATH)

### A. Fungsi `generateKey()` ($V(G) = 1$)
*   **Path ID**: `P-AES-G1`
*   **Control Flow**: Start $\rightarrow$ `bin2hex()` $\rightarrow$ End
*   **Test Method**: `generate_key_creates_valid_aes_key`
*   **Test File**: `tests/Unit/Kriptografi/AesHelperTest.php`
*   **Expected Behaviour**: Menghasilkan string heksadesimal acak 32 karakter.
*   **Actual Result**: PASS
*   **Coverage Status**: COVERED

### B. Fungsi `encryptWithKey($plain, $key)` ($V(G) = 2$)
*   **Path ID**: `P-AES-E1`
    *   *Condition*: `$plain === null`
    *   *Test Method*: `encrypt_null_plain_returns_null`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected Behaviour*: Mengembalikan nilai `null`.
    *   *Actual Result*: PASS
    *   *Coverage Status*: COVERED
*   **Path ID**: `P-AES-E2`
    *   *Condition*: `$plain` valid string
    *   *Test Method*: `encrypt_decrypt_text_returns_original`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected Behaviour*: Mengembalikan cipher-text terenkripsi AES base64.
    *   *Actual Result*: PASS
    *   *Coverage Status*: COVERED

### C. Fungsi `decryptWithKey($encrypted, $key)` ($V(G) = 3$)
*   **Path ID**: `P-AES-D1`
    *   *Condition*: `$encrypted === null`
    *   *Test Method*: `decrypt_null_encrypted_returns_null`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Mengembalikan `null` / PASS / COVERED
*   **Path ID**: `P-AES-D2`
    *   *Condition*: Decoded ciphertext $< 16$ byte (panjang IV minimum)
    *   *Test Method*: `decrypt_tampered_ciphertext_fails`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Mengembalikan `null` / PASS / COVERED
*   **Path ID**: `P-AES-D3`
    *   *Condition*: Decoded data $\ge 16$ byte (memanggil `openssl_decrypt`)
    *   *Test Method*: `encrypt_decrypt_text_returns_original`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Mengembalikan teks asli terdekripsi / PASS / COVERED

### D. Fungsi `encrypt($plain)` ($V(G) = 2$)
*   **Path ID**: `P-AES-E3`
    *   *Condition*: `$plain` static = null
    *   *Test Method*: `encrypt_static_null_plain_returns_null`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Mengembalikan `null` / PASS / COVERED
*   **Path ID**: `P-AES-E4`
    *   *Condition*: `$plain` static valid
    *   *Test Method*: `encrypt_static_valid_plain_returns_ciphertext`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Mengembalikan cipher-text statis / PASS / COVERED

### E. Fungsi `decrypt($encrypted, $key = null)` ($V(G) = 5$)
*   **Path ID**: `P-DEC-1`
    *   *Condition*: `$encrypted` static = null
    *   *Test Method*: `decrypt_static_null_encrypted_returns_null`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Mengembalikan `null` / PASS / COVERED
*   **Path ID**: `P-DEC-2`
    *   *Condition*: `$key` dynamic valid heksadesimal 32 karakter
    *   *Test Method*: `satgas_can_decrypt_aduan_with_valid_pin`
    *   *Test File*: `tests/Feature/SatgasControllerTest.php`
    *   *Expected/Actual*: Mengalihkan dekripsi ke `decryptWithKey()` / PASS / COVERED
*   **Path ID**: `P-DEC-3`
    *   *Condition*: `$key = null` (statis)
    *   *Test Method*: `decrypt_static_valid_encrypted_returns_plain`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Menggunakan staticKey() untuk dekripsi / PASS / COVERED
*   **Path ID**: `P-DEC-4`
    *   *Condition*: `$key` invalid, decoded ciphertext $< 16$ byte
    *   *Test Method*: `decrypt_short_ciphertext_returns_null`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Mengembalikan `null` / PASS / COVERED
*   **Path ID**: `P-DEC-5`
    *   *Condition*: `$key` invalid, decoded ciphertext $\ge 16$ byte
    *   *Test Method*: `decrypt_invalid_key_format_falls_back_to_hashing`
    *   *Test File*: `tests/Unit/Kriptografi/AesHelperTest.php`
    *   *Expected/Actual*: Melakukan fallback hashing key dan dekripsi / PASS / COVERED

### F. Fungsi `encryptKey($aesKey)` ($V(G) = 3$)
*   **Path ID**: `P-RSA-E1`
    *   *Condition*: PEM public key tidak ada
    *   *Test Method*: `encrypt_with_missing_public_key`
    *   *Test File*: `tests/Unit/Kriptografi/RsaHelperTest.php`
    *   *Expected/Actual*: Melempar Exception (Public key not found) / PASS / COVERED
*   **Path ID**: `P-RSA-E2`
    *   *Condition*: Public key ada, proses `openssl_public_encrypt` sukses
    *   *Test Method*: `encrypt_aes_key_with_public_key`
    *   *Test File*: `tests/Unit/Kriptografi/RsaHelperTest.php`
    *   *Expected/Actual*: Mengembalikan string Base64 kunci terenkripsi / PASS / COVERED
*   **Path ID**: `P-RSA-E3`
    *   *Condition*: Public key ada, proses `openssl_public_encrypt` gagal (data $> 245$ bytes)
    *   *Test Method*: `encrypt_large_data_fails_rsa_encryption`
    *   *Test File*: `tests/Unit/Kriptografi/RsaHelperTest.php`
    *   *Expected/Actual*: Melempar Exception (RSA Encryption failed) / PASS / COVERED

### G. Fungsi `decryptKey($encryptedAesKeyBase64)` ($V(G) = 3$)
*   **Path ID**: `P-RSA-D1`
    *   *Condition*: PEM private key tidak ada
    *   *Test Method*: `decrypt_with_missing_private_key`
    *   *Test File*: `tests/Unit/Kriptografi/RsaHelperTest.php`
    *   *Expected/Actual*: Melempar Exception (Private key not found) / PASS / COVERED
*   **Path ID**: `P-RSA-D2`
    *   *Condition*: Private key ada, proses `openssl_private_decrypt` sukses
    *   *Test Method*: `decrypt_aes_key_with_private_key`
    *   *Test File*: `tests/Unit/Kriptografi/RsaHelperTest.php`
    *   *Expected/Actual*: Mengembalikan decrypted AES Key / PASS / COVERED
*   **Path ID**: `P-RSA-D3`
    *   *Condition*: Private key ada, proses `openssl_private_decrypt` gagal (cipher rusak)
    *   *Test Method*: `decrypt_corrupted_rsa_cipher_fails`
    *   *Test File*: `tests/Unit/Kriptografi/RsaHelperTest.php`
    *   *Expected/Actual*: Melempar Exception (RSA Decryption failed) / PASS / COVERED

---

## 6. ANALISIS LOGIKA JALUR PENTING

1.  **`decryptWithKey()`**: Fungsi ini memiliki tepat 3 independent path. Penanganan kegagalan OpenSSL (wrong key) mengembalikan boolean `false` yang dialirkan langsung tanpa control-flow/branch baru di kode, sehingga tidak dihitung sebagai independent path ke-4.
2.  **`decrypt()`**: Memiliki 4 decision point (early null check, validasi dynamic key format, ternary static/dynamic key select, dan minimum length IV). Menghasilkan tepat 5 basis path yang valid.
3.  **RSA Key-Pair**: Memiliki exception flow saat file PEM hilang (P-RSA-E1 dan P-RSA-D1) serta kegagalan matematis modul OpenSSL (P-RSA-E3 dan P-RSA-D3).

---

## 7. PATH COVERAGE & KELAYAKAN
*   **Total Independent Path**: 19
*   **Covered (Teruji)**: 19
*   **Uncovered**: 0
*   **Path Coverage**:
    $$\text{Path Coverage} = \frac{19}{19} \times 100\% = 100.00\%$$

*Pernyataan Kelayakan: Angka 100% ini membuktikan cakupan menyeluruh terhadap 19 basis path logika internal helper kriptografi, bukan klaim bahwa seluruh project atau seluruh aspek sistem 100% bebas dari celah keamanan.*

---

## 8. STATUS SKIPPED TEST & HASIL PHPUNIT AKTUAL

Berikut adalah 3 test skipped pada test suite Anda:
1.  `test_aduan_validation_manual` (Feature Test)
2.  `test_hybrid_encryption_performance` (Feature Test)
3.  `decrypt_with_wrong_private_key` (Unit Test)

Ketiga skipped test ini **tidak memengaruhi** Path Coverage karena jalur asimetris kegagalan dekripsi RSA (**P-RSA-D3**) sudah teruji aman dan **PASS** via `decrypt_corrupted_rsa_cipher_fails()`.

### Hasil Akhir PHPUnit Suite:
*   Total Tests: 73
*   Passed: 70
*   Skipped: 3
*   Failed: 0
*   Errors: 0

---

## 9. PERBEDAAN METODOLOGI TESTING LAIN

*   **White Box Audit**: Logika internal & path coverage helper kriptografi.
*   **Validation (`hasil_validasi.csv`)**: Functional/Validation testing secara Black Box (PDF, JPG, required, boundary).
*   **Performance (`hasil_pengujian.csv`)**: Performance testing (Waktu enkripsi/dekripsi, SHA-256 Checksum).
*   **Integrity**: Memverifikasi keutuhan berkas (SHA-256 asli vs hasil dekripsi identik 100%).

---

## 10. FILE BUKTI TESTING (HANDOVER PACKAGE)

*   Unit Test: [`AesHelperTest.php`](file:///d:/ppkpt/tests/Unit/Kriptografi/AesHelperTest.php) dan [`RsaHelperTest.php`](file:///d:/ppkpt/tests/Unit/Kriptografi/RsaHelperTest.php)
*   Feature Test: [`AduanValidationFeatureTest.php`](file:///d:/ppkpt/tests/Feature/AduanValidationFeatureTest.php) dan [`HybridEncryptionPerformanceTest.php`](file:///d:/ppkpt/tests/Feature/HybridEncryptionPerformanceTest.php)
*   Hasil CSV: [`hasil_validasi.csv`](file:///d:/ppkpt/storage/app/testing/hasil_validasi.csv) dan [`hasil_pengujian.csv`](file:///d:/ppkpt/storage/app/testing/hasil_pengujian.csv)
*   Laporan Audit: [`FINAL_PRE_HANDOVER_AUDIT.md`](file:///d:/ppkpt/FINAL_PRE_HANDOVER_AUDIT.md)

---

## 11. KEAMANAN DATA (SECURITY ALERT)

*   Berkas `.env`, PEM private/public keys, dan kredensial database **TIDAK** disertakan dalam paket ini. Pengujian lokal harus membuat kunci mandiri dengan command: `php artisan rsa:generate`.
