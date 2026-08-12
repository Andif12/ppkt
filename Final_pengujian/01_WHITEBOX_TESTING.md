# LAPORAN WHITE BOX TESTING (BASIS PATH TESTING)

Laporan ini mendokumentasikan metodologi, rancangan, analisis alur kontrol, dan hasil akhir pengujian *White Box* (Basis Path Testing) pada helper kriptografi proyek **Sistem Informasi Pengaduan Kekerasan (PPKPT ITH)**.

---

## A. TUJUAN PENGUJIAN

*   **Definisi & Konteks**: *White Box Testing* adalah metode pengujian perangkat lunak di mana struktur internal, rancangan, dan kode program diperiksa secara langsung.
*   **Tujuan Penggunaan**: Untuk menjamin seluruh alur kontrol logika pada helper kriptografi hybrid (AES-256 + RSA-2048) telah dieksekusi minimal satu kali tanpa ada percabangan logika (*dead-branch*) yang terlewat.
*   **Fokus Pengujian**: Struktur logika internal helper, penanganan pengecualian (*exception handling*), serta penanganan nilai kosong/null.
*   **Metodologi**: **Basis Path Testing** untuk mendefinisikan himpunan jalur independen (*basis set*) berdasarkan kompleksitas siklomatis kode sumber.

---

## B. SOURCE CODE YANG DIUJI

Pengujian difokuskan pada dua helper kriptografi utama:

### 1. `app/Helpers/AesHelper.php`
*   `generateKey()`: Membuat kunci simetris 256-bit acak secara aman (berupa 32 karakter heksadesimal).
*   `encryptWithKey($plain, $key)`: Mengenkripsi plain-text dengan kunci AES dinamis dan inisialisasi IV 16-byte acak.
*   `decryptWithKey($encrypted, $key)`: Mendekripsi cipher-text menggunakan kunci dinamis dan memisahkan biner IV.
*   `encrypt($plain)`: Enkripsi biner dengan kunci statis bawaan aplikasi.
*   `decrypt($encrypted, $key = null)`: Dekripsi cipher-text dinamis/statis dengan deteksi otomatis tipe kunci dan fallback heksadesimal hashing.

### 2. `app/Helpers/RsaHelper.php`
*   `encryptKey($aesKey)`: Enkripsi asimetris kunci AES menggunakan kunci publik RSA-2048 (.pem).
*   `decryptKey($encryptedAesKeyBase64)`: Dekripsi kunci AES terenkripsi menggunakan kunci privat RSA-2048 (.pem).

---

## C. CYCLOMATIC COMPLEXITY (SENSITIVITAS PREDICATE-LEVEL)

Penghitungan decision point ($D$) dan Cyclomatic Complexity ($V(G)$) menggunakan aturan **Predicate-Level McCabe**:
*   Setiap `if` dihitung sebagai 1 decision point.
*   Setiap `elseif` dihitung sebagai 1 decision point.
*   Setiap ternary `? :` dihitung sebagai 1 decision point.
*   Klausa logika boolean compound (seperti `&&` dan `||` di dalam satu `if`) **tidak** dihitung sebagai decision point tambahan.

### Rumus Kompleksitas Siklomatis:
$$V(G) = D + 1$$
$$V(G) = E - N + 2$$

*   $D$ = Jumlah Decision Point.
*   $E$ = Jumlah Edge (jalur kontrol pada graf).
*   $N$ = Jumlah Node (pernyataan/instruksi graf).

### Tabel Cyclomatic Complexity Final:

| Fungsi | Decision (D) | V(G) | E | N | E-N+2 | Independent Path |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| `generateKey()` | 0 | 1 | 1 | 2 | 1 | 1 |
| `encryptWithKey()` | 1 | 2 | 5 | 5 | 2 | 2 |
| `decryptWithKey()` | 2 | 3 | 8 | 7 | 3 | 3 |
| `encrypt()` | 1 | 2 | 5 | 5 | 2 | 2 |
| `decrypt()` | 4 | 5 | 14 | 11 | 5 | 5 |
| `encryptKey()` | 2 | 3 | 8 | 7 | 3 | 3 |
| `decryptKey()` | 2 | 3 | 8 | 7 | 3 | 3 |
| **TOTAL** | **12** | **19** | - | - | - | **19** |

*Catatan: Total V(G) = 19 merupakan penjumlahan kompleksitas siklomatis dari ketujuh fungsi pembantu kriptografi secara terpisah.*

---

## D. DETAIL PEMETAAN JALUR INDEPENDEN (INDEPENDENT PATH)

Seluruh 19 Independent Path dipetakan langsung dengan method pengujian PHPUnit yang berstatus **PASS**:

| Path ID | Function | Kondisi / Alur Kontrol | Test Method | Hasil |
| :--- | :--- | :--- | :--- | :---: |
| **P-AES-G1** | `generateKey` | Normal flow generator key | `generate_key_creates_valid_aes_key` | PASS |
| **P-AES-E1** | `encryptWithKey` | `$plain === null` | `encrypt_null_plain_returns_null` | PASS |
| **P-AES-E2** | `encryptWithKey` | `$plain` valid string | `encrypt_decrypt_text_returns_original` | PASS |
| **P-AES-D1** | `decryptWithKey` | `$encrypted === null` | `decrypt_null_encrypted_returns_null` | PASS |
| **P-AES-D2** | `decryptWithKey` | Decoded data $< 16$ byte | `decrypt_tampered_ciphertext_fails` | PASS |
| **P-AES-D3** | `decryptWithKey` | Decoded data $\ge 16$ byte | `encrypt_decrypt_text_returns_original` | PASS |
| **P-AES-E3** | `encrypt` | `$plain` static = null | `encrypt_static_null_plain_returns_null` | PASS |
| **P-AES-E4** | `encrypt` | `$plain` static valid | `encrypt_static_valid_plain_returns_ciphertext` | PASS |
| **P-DEC-1** | `decrypt` | `$encrypted` static = null | `decrypt_static_null_encrypted_returns_null` | PASS |
| **P-DEC-2** | `decrypt` | `$key` dynamic format heksadesimal 32 karakter | `satgas_can_decrypt_aduan_with_valid_pin` | PASS |
| **P-DEC-3** | `decrypt` | `$key = null` (statis) | `decrypt_static_valid_encrypted_returns_plain` | PASS |
| **P-DEC-4** | `decrypt` | `$key` invalid, decoded ciphertext $< 16$ byte | `decrypt_short_ciphertext_returns_null` | PASS |
| **P-DEC-5** | `decrypt` | `$key` invalid, decoded ciphertext $\ge 16$ byte | `decrypt_invalid_key_format_falls_back_to_hashing` | PASS |
| **P-RSA-E1** | `encryptKey` | Public key PEM tidak ditemukan | `encrypt_with_missing_public_key` | PASS |
| **P-RSA-E2** | `encryptKey` | Public key ditemukan, OpenSSL success | `encrypt_aes_key_with_public_key` | PASS |
| **P-RSA-E3** | `encryptKey` | Public key ditemukan, data $> 245$ bytes | `encrypt_large_data_fails_rsa_encryption` | PASS |
| **P-RSA-D1** | `decryptKey` | Private key PEM tidak ditemukan | `decrypt_with_missing_private_key` | PASS |
| **P-RSA-D2** | `decryptKey` | Private key ditemukan, OpenSSL success | `decrypt_aes_key_with_private_key` | PASS |
| **P-RSA-D3** | `decryptKey` | Private key ditemukan, cipher rusak | `decrypt_corrupted_rsa_cipher_fails` | PASS |

---

## E. PATH COVERAGE
*   **Total Independent Path**: 19 Jalur
*   **Jalur Teruji (*Covered*)**: 19 Jalur
*   **Jalur Tidak Teruji (*Uncovered*)**: 0 Jalur
*   **Path Coverage Rate**:
    $$\text{Path Coverage} = \frac{19}{19} \times 100\% = 100.00\%$$

*Batasan Interpretasi: Path coverage 100.00% menjamin bahwa setiap jalur logika percabangan di dalam helper kriptografi telah dieksekusi penuh oleh unit testing. Ini bukan garansi mutlak bahwa seluruh aspek keamanan sistem bebas dari celah di luar cakupan fungsi pembantu tersebut.*

---

## F. HASIL EKSEKUSI PHPUNIT SUITE
*   **Total Tests**: 73
*   **Passed (Lolos)**: 70
*   **Skipped (Dilewati)**: 3
*   **Failed (Gagal)**: 0
*   **Errors (Galat)**: 0

### Analisis Skipped Test (Dilewati):
1.  `test_aduan_validation_manual`: Pengujian manual input dinamis (membutuhkan variabel `$env:TEST_FILE` di terminal).
2.  `test_hybrid_encryption_performance`: Uji performa dinamis file fisik (membutuhkan variabel `$env:TEST_FILE`).
3.  `decrypt_with_wrong_private_key`: Pengujian asimetris lokal Windows yang membutuhkan file konfigurasi `openssl.cnf`.

*Ketiga skipped test ini **tidak memengaruhi** 100.00% White Box Path Coverage karena jalur kegagalan asimetris (**P-RSA-D3**) telah tercakup penuh oleh unit test `decrypt_corrupted_rsa_cipher_fails()` yang berstatus **PASS**.*

---

## G. HUBUNGAN METODOLOGI PENGUJIAN LAIN

*   **White Box Testing**: Menguji struktur logika internal dan kebenaran alur program di tingkat kode sumber helper.
*   **Validation Testing (Black Box)**: Memverifikasi validasi form masukan pelapor di level antarmuka/fitur (tipe berkas, ukuran, batas karakter). Hasil dicatat pada `hasil_validasi.csv`.
*   **Performance Testing**: Mengukur kecepatan eksekusi proses enkripsi dan dekripsi hybrid. Hasil dicatat pada `hasil_pengujian.csv`.
*   **Integrity Testing**: Menjamin integritas data berkas pengaduan (membandingkan hash SHA-256 berkas asli dan terdekripsi).

---

## H. FILE BUKTI PENGUJIAN

*   Unit Test AES: `tests/Unit/Kriptografi/AesHelperTest.php`
*   Unit Test RSA: `tests/Unit/Kriptografi/RsaHelperTest.php`
*   Feature Validation: `tests/Feature/AduanValidationFeatureTest.php`
*   Feature Performance: `tests/Feature/HybridEncryptionPerformanceTest.php`
*   Validation Log: `storage/app/testing/hasil_validasi.csv`
*   Performance Log: `storage/app/testing/hasil_pengujian.csv`

*Pernyataan Keamanan: Berkas sensitif seperti kredensial `.env`, PEM private key, password database, dan API key tidak dimasukkan ke dalam paket pengujian.*
