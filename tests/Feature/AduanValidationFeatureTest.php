<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Aduan;
use App\Helpers\AesHelper;
use App\Helpers\RsaHelper;

class AduanValidationFeatureTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan Kunci RSA tersedia
        if (!file_exists(storage_path('app/private_key.pem')) || !file_exists(storage_path('app/public_key.pem'))) {
            $config = [
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            ];
            $res = openssl_pkey_new($config);
            if ($res) {
                openssl_pkey_export($res, $privKey);
                $pubKey = openssl_pkey_get_details($res);
                $pubKey = $pubKey["key"];
                file_put_contents(storage_path('app/private_key.pem'), $privKey);
                file_put_contents(storage_path('app/public_key.pem'), $pubKey);
            }
        }

        $this->user = User::factory()->create(['role' => 'pelapor']);
    }

    /** @test */
    public function test_aduan_validation_manual()
    {
        $filePath = getenv('TEST_FILE');
        if (!$filePath) {
            $this->markTestSkipped("Variabel lingkungan 'TEST_FILE' tidak diatur. Pengujian validasi manual dilewati.");
            return;
        }

        if (!file_exists($filePath)) {
            $this->fail("File tidak ditemukan di path: " . $filePath);
            return;
        }

        $fileName = basename($filePath);
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Dapatkan MIME type asli file biner
        $realMimeType = mime_content_type($filePath);
        $fileSizeInBytes = filesize($filePath);
        $fileSizeInKb = round($fileSizeInBytes / 1024, 2);
        $fileSizeStr = $fileSizeInKb . ' KB';

        // Tentukan input target yang sedang diuji (default: pernyataan_pelapor)
        $targetInput = getenv('TEST_INPUT') ?: 'pernyataan_pelapor';

        // Tentukan expected result berdasarkan aturan validasi UserController
        $expectedResult = 'ACCEPT';
        $reasons = [];

        if ($targetInput === 'bukti_pelaporan') {
            // Aturan validasi bukti_pelaporan:
            // mimes:jpg,jpeg,png,mp4,mov,avi,mp3,wav,pdf,ogg | max:10240
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'mp4', 'mov', 'avi', 'mp3', 'wav', 'pdf', 'ogg'];
            $allowedMimes = [
                'image/jpeg', 'image/jpg', 'image/png', 'video/mp4', 'video/quicktime',
                'video/x-msvideo', 'audio/mpeg', 'audio/wav', 'audio/x-wav',
                'application/pdf', 'audio/ogg', 'video/ogg', 'application/ogg'
            ];
            $maxSize = 10240; // 10MB
        } else {
            // Aturan validasi pernyataan_pelapor (default):
            // mimes:pdf,doc,docx | max:2048
            $allowedExtensions = ['pdf', 'doc', 'docx'];
            $allowedMimes = [
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $maxSize = 2048; // 2MB
        }

        if (!in_array($fileExtension, $allowedExtensions) || !in_array($realMimeType, $allowedMimes)) {
            $expectedResult = 'REJECT';
            $reasons[] = 'Ekstensi atau tipe MIME tidak didukung untuk ' . $targetInput;
        }

        if ($fileSizeInKb > $maxSize) {
            $expectedResult = 'REJECT';
            $reasons[] = 'Ukuran file melebihi batas ' . $maxSize . ' KB';
        }

        $namaLength = getenv('TEST_NAMA_LENGTH') ? (int)getenv('TEST_NAMA_LENGTH') : 50;
        if ($namaLength > 255) {
            $expectedResult = 'REJECT';
            $reasons[] = 'Nama pelapor melebihi batas 255 karakter';
        }

        $email = getenv('TEST_EMAIL') ?: 'pelapor.valid@example.com';
        if (strpos($email, '@') === false) {
            $expectedResult = 'REJECT';
            $reasons[] = 'Format email tidak valid';
        }

        $fieldEmpty = getenv('TEST_FIELD_EMPTY');
        if ($fieldEmpty === 'nama_pelapor' || $fieldEmpty === 'pernyataan_pelapor') {
            $expectedResult = 'REJECT';
            $reasons[] = 'Field wajib ' . $fieldEmpty . ' kosong';
        }

        $testMode = getenv('TEST_MODE') ?: 'normal';
        if ($testMode === 'rsa_missing') {
            $expectedResult = 'REJECT';
            $reasons[] = 'Kunci asimetris RSA hilang';
        }

        // Buat instance UploadedFile dari berkas fisik riil
        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $filePath,
            $fileName,
            $realMimeType,
            null,
            true // test mode
        );

        $payload = $this->getBasePayload();
        
        if ($targetInput === 'bukti_pelaporan') {
            $payload['bukti_pelaporan'] = $uploadedFile;
            // pernyataan_pelapor harus tetap ada file PDF dummy valid agar form tidak menolak karena kosong
            $payload['pernyataan_pelapor'] = UploadedFile::fake()->create('pernyataan_wajib.pdf', 50, 'application/pdf');
        } else {
            $payload['pernyataan_pelapor'] = $uploadedFile;
            $payload['bukti_pelaporan'] = null;
        }

        $payload['nama_pelapor'] = str_repeat('A', $namaLength);
        $payload['email_pelapor'] = $email;

        if ($fieldEmpty) {
            unset($payload[$fieldEmpty]);
        }

        $scenarioTitle = sprintf('Manual: Uji %s sebagai %s', strtoupper($fileExtension), $targetInput);

        if ($testMode === 'rsa_missing') {
            $pubKeyPath = storage_path('app/public_key.pem');
            $tempPubKeyPath = storage_path('app/public_key.pem.bak');
            if (file_exists($pubKeyPath)) {
                rename($pubKeyPath, $tempPubKeyPath);
            }

            try {
                $this->runScenario(
                    $scenarioTitle,
                    $payload,
                    $expectedResult,
                    $fileName,
                    $fileExtension,
                    $realMimeType,
                    $fileSizeStr,
                    $namaLength
                );
            } finally {
                if (file_exists($tempPubKeyPath)) {
                    rename($tempPubKeyPath, $pubKeyPath);
                }
            }
        } else {
            $this->runScenario(
                $scenarioTitle,
                $payload,
                $expectedResult,
                $fileName,
                $fileExtension,
                $realMimeType,
                $fileSizeStr,
                $namaLength
            );
        }
    }

    /**
     * Dapatkan payload default yang valid
     */
    protected function getBasePayload(array $overrides = [])
    {
        return array_merge([
            'category' => 'Dosen',
            'alamat_pelapor' => 'Alamat Pelapor Resmi',
            'pernyataan_pelapor' => UploadedFile::fake()->create('pernyataan.pdf', 100, 'application/pdf'),
            'email_pelapor' => 'pelapor.valid@example.com',
            'phone_pelapor' => '081234567890',
            'hubungi' => 'Email',
            'nama_korban' => 'Korban Kasus',
            'jenis_kelamin_korban' => 'Perempuan',
            'status_korban' => 'Mahasiswa',
            'nama_terlapor' => 'Terlapor Pelaku',
            'jenis_kelamin_terlapor' => 'Laki-laki',
            'status_terlapor' => 'Dosen',
            'karakteristik_terlapor' => 'Tinggi besar berkacamata',
            'terlapor' => 'Individu',
            'warning' => 'Tidak',
            'tanggal_peristiwa' => '2026-08-01',
            'chronology' => 'Kronologi lengkap peristiwa pelecehan verbal di koridor gedung FISIP.',
            'bersedia' => 'Ya',
            'prioritas' => 'Tinggi',
            'lokasi' => 'Gedung FISIP Kampus A',
            'nama_pelapor' => 'Saksi Rahasia',
            'dampak_fisik' => 3,
            'dampak_psikologis' => 4,
            'keseriusan' => 4,
            'berpotensi' => 3,
            'berulang' => 2,
            'kinerja' => 4,
            'hubungan_sosial' => 3,
            'lingkungan' => 4,
        ], $overrides);
    }

    /**
     * Helper utama untuk menjalankan skenario uji dan mencatat ke CSV
     */
    protected function runScenario(
        string $scenarioName,
        array $payload,
        string $expectedResult,
        ?string $fileName,
        ?string $extension,
        ?string $mimeType,
        ?string $fileSize,
        int $charCount
    ) {
        Storage::fake('public');
        $start = microtime(true);

        $validationStatus = 'PASS';
        $encryptionStatus = 'N/A';
        $submissionStatus = 'SUCCESS';
        $actualResult = 'ACCEPT';
        $errorMessage = '';

        try {
            $response = $this->actingAs($this->user)->post('/user', $payload);
            $executionTime = microtime(true) - $start;

            // Cek apakah ada error validasi di session (Laravel standard redirect back)
            if (session()->has('errors')) {
                $validationStatus = 'FAIL';
                $actualResult = 'REJECT';
                $submissionStatus = 'REJECTED';
                
                $errors = session('errors')->getBag('default')->getMessages();
                $errorMsgs = [];
                foreach ($errors as $field => $messages) {
                    $errorMsgs[] = $field . ': ' . implode(', ', $messages);
                }
                $errorMessage = implode(' | ', $errorMsgs);
            } else {
                // Berhasil diterima, cek status database dan enkripsi
                $aduan = Aduan::latest()->first();
                if ($aduan) {
                    if (!empty($aduan->encrypted_aes_key)) {
                        $encryptionStatus = 'PASS';
                    } else {
                        $encryptionStatus = 'FAIL';
                        $actualResult = 'REJECT';
                        $submissionStatus = 'REJECTED';
                        $errorMessage = 'Enkripsi Gagal: Kunci AES terenkripsi RSA kosong.';
                    }
                } else {
                    $actualResult = 'REJECT';
                    $submissionStatus = 'REJECTED';
                    $errorMessage = 'Aduan tidak tersimpan di database.';
                }
            }
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $start;
            $validationStatus = 'PASS'; // Validasi form lolos, tapi server crash
            $encryptionStatus = 'FAIL';
            $submissionStatus = 'REJECTED';
            $actualResult = 'REJECT';
            $errorMessage = $e->getMessage();
        }

        // Test Result (Status Akhir)
        $statusAkhir = ($expectedResult === $actualResult) ? 'PASS' : 'FAIL';

        $fieldUpload = 'N/A';
        if (isset($payload['pernyataan_pelapor']) && $payload['pernyataan_pelapor'] !== null) {
            $fieldUpload = 'pernyataan_pelapor';
        } elseif (isset($payload['bukti_pelaporan']) && $payload['bukti_pelaporan'] !== null) {
            $fieldUpload = 'bukti_pelaporan';
        }

        // Tulis ke CSV
        $no = $this->logToCsv([
            'scenarioName' => $scenarioName,
            'fieldUpload' => $fieldUpload,
            'fileName' => $fileName ?: 'N/A',
            'extension' => $extension ?: 'N/A',
            'mimeType' => $mimeType ?: 'N/A',
            'fileSize' => $fileSize ?: 'N/A',
            'charCount' => $charCount,
            'expected' => $expectedResult,
            'actual' => $actualResult,
            'valStatus' => $validationStatus,
            'encStatus' => $encryptionStatus,
            'subStatus' => $submissionStatus,
            'statusAkhir' => $statusAkhir,
            'error' => $errorMessage,
            'time' => $executionTime
        ]);

        // Cetak output ke terminal
        $this->printTerminalSummary($no, $scenarioName, $expectedResult, $actualResult, $statusAkhir, $errorMessage, $executionTime);

        // Assert untuk PHPUnit
        $this->assertEquals('PASS', $statusAkhir);
    }

    private function logToCsv(array $data)
    {
        $dirPath = storage_path('app/testing');
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $csvPath = $dirPath . '/hasil_validasi.csv';
        $fileExists = file_exists($csvPath);

        // Jika file belum ada, tulis header 17 kolom
        if (!$fileExists) {
            $header = [
                'No',
                'Waktu Pengujian',
                'Nama Skenario',
                'Field',
                'Nama File',
                'Extension',
                'MIME Type',
                'Ukuran File',
                'Jumlah Karakter',
                'Expected Result',
                'Actual Result',
                'Validation Status',
                'Encryption Status',
                'Submission Status',
                'Status Akhir',
                'Error Message',
                'Execution Time'
            ];
            $file = fopen($csvPath, 'w');
            fputcsv($file, $header);
            fclose($file);
        }

        // Tentukan Nomor Urut otomatis
        $no = 1;
        if (file_exists($csvPath)) {
            $file = fopen($csvPath, 'r');
            $lineCount = 0;
            while (fgetcsv($file) !== false) {
                $lineCount++;
            }
            fclose($file);
            if ($lineCount > 0) {
                $no = $lineCount; 
            }
        }

        $row = [
            $no,
            date('Y-m-d H:i:s'),
            $data['scenarioName'],
            $data['fieldUpload'] ?? 'N/A',
            $data['fileName'],
            $data['extension'],
            $data['mimeType'],
            $data['fileSize'],
            $data['charCount'],
            $data['expected'],
            $data['actual'],
            $data['valStatus'],
            $data['encStatus'],
            $data['subStatus'],
            $data['statusAkhir'],
            $data['error'] ?: '',
            number_format($data['time'], 4)
        ];

        $file = fopen($csvPath, 'a');
        fputcsv($file, $row);
        fclose($file);

        return $no;
    }

    private function printTerminalSummary($no, $scenario, $expected, $actual, $status, $error, $time)
    {
        // Hitung akumulasi untuk pencetakan summary
        $csvPath = storage_path('app/testing/hasil_validasi.csv');
        $total = 0;
        $passed = 0;
        $failed = 0;
        
        if (file_exists($csvPath)) {
            if (($handle = fopen($csvPath, "r")) !== false) {
                fgetcsv($handle); // skip header
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 17) continue;
                    $total++;
                    if ($row[14] === 'PASS') {
                        $passed++;
                    } else {
                        $failed++;
                    }
                }
                fclose($handle);
            }
        }
        
        $successRate = $total > 0 ? ($passed / $total) * 100 : 0;

        $output = "\n";
        $output .= "==================================================\n";
        $output .= sprintf("SKENARIO VALIDASI #%d: %s\n", $no, $scenario);
        $output .= "==================================================\n";
        $output .= sprintf("Expected Result   : %s\n", $expected);
        $output .= sprintf("Actual Result     : %s\n", $actual);
        $output .= sprintf("Test Status       : %s\n", $status);
        $output .= sprintf("Waktu Eksekusi    : %.4f s\n", $time);
        if ($error) {
            $output .= sprintf("Pesan Kesalahan   : %s\n", $error);
        }
        $output .= "--------------------------------------------------\n";
        $output .= "SUMMARY VALIDASI (KUMULATIF)\n";
        $output .= "--------------------------------------------------\n";
        $output .= sprintf("Total Skenario    : %d\n", $total);
        $output .= sprintf("Passed            : %d\n", $passed);
        $output .= sprintf("Failed            : %d\n", $failed);
        $output .= sprintf("Success Rate      : %.2f %%\n", $successRate);
        $output .= "==================================================\n";

        echo $output;
    }

    // =========================================================================
    // SKENARIO VALID (EXPECTED = ACCEPT)
    // =========================================================================

    /** @test */
    public function test_scenario_val_1_pdf_valid()
    {
        $file = UploadedFile::fake()->create('pernyataan.pdf', 100, 'application/pdf');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-VAL-1: PDF Valid + Data Valid', $payload, 'ACCEPT', 'pernyataan.pdf', 'pdf', 'application/pdf', '100 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_val_2_jpg_valid()
    {
        $file = UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg');
        $payload = $this->getBasePayload(['bukti_pelaporan' => $file]);

        $this->runScenario('S-VAL-2: JPG Valid + Data Valid', $payload, 'ACCEPT', 'bukti.jpg', 'jpg', 'image/jpeg', '200 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_val_3_png_valid()
    {
        $file = UploadedFile::fake()->create('bukti.png', 150, 'image/png');
        $payload = $this->getBasePayload(['bukti_pelaporan' => $file]);

        $this->runScenario('S-VAL-3: PNG Valid + Data Valid', $payload, 'ACCEPT', 'bukti.png', 'png', 'image/png', '150 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_val_4_docx_valid()
    {
        $file = UploadedFile::fake()->create('pernyataan.docx', 300, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-VAL-4: DOCX Valid + Data Valid', $payload, 'ACCEPT', 'pernyataan.docx', 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '300 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_val_5_file_sangat_kecil()
    {
        $file = UploadedFile::fake()->create('pernyataan.pdf', 5, 'application/pdf');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-VAL-5: Ukuran Berkas Sangat Kecil (5 KB)', $payload, 'ACCEPT', 'pernyataan.pdf', 'pdf', 'application/pdf', '5 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_val_6_file_pernyataan_mendekati_batas()
    {
        $file = UploadedFile::fake()->create('pernyataan.pdf', 2000, 'application/pdf');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-VAL-6: Berkas Pernyataan Mendekati Batas (2000 KB)', $payload, 'ACCEPT', 'pernyataan.pdf', 'pdf', 'application/pdf', '2000 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_val_7_file_pernyataan_tepat_batas()
    {
        $file = UploadedFile::fake()->create('pernyataan.pdf', 2048, 'application/pdf');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-VAL-7: Berkas Pernyataan Tepat Batas (2048 KB)', $payload, 'ACCEPT', 'pernyataan.pdf', 'pdf', 'application/pdf', '2048 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_val_8_nama_pelapor_di_bawah_batas()
    {
        $payload = $this->getBasePayload(['nama_pelapor' => str_repeat('A', 50)]);

        $this->runScenario('S-VAL-8: Nama Pelapor di Bawah Batas (50 Karakter)', $payload, 'ACCEPT', 'pernyataan.pdf', 'pdf', 'application/pdf', '100 KB', 50);
    }

    /** @test */
    public function test_scenario_val_9_nama_pelapor_tepat_batas()
    {
        $payload = $this->getBasePayload(['nama_pelapor' => str_repeat('A', 255)]);

        $this->runScenario('S-VAL-9: Nama Pelapor Tepat Batas (255 Karakter)', $payload, 'ACCEPT', 'pernyataan.pdf', 'pdf', 'application/pdf', '100 KB', 255);
    }

    // =========================================================================
    // SKENARIO INVALID (EXPECTED = REJECT)
    // =========================================================================

    /** @test */
    public function test_scenario_inv_1_file_txt()
    {
        $file = UploadedFile::fake()->create('pernyataan.txt', 100, 'text/plain');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-INV-1: Berkas Pernyataan Format TXT (Ditolak)', $payload, 'REJECT', 'pernyataan.txt', 'txt', 'text/plain', '100 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_inv_2_file_zip()
    {
        $file = UploadedFile::fake()->create('pernyataan.zip', 100, 'application/zip');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-INV-2: Berkas Pernyataan Format ZIP (Ditolak)', $payload, 'REJECT', 'pernyataan.zip', 'zip', 'application/zip', '100 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_inv_3_mime_spoofing()
    {
        // Extension pdf, tapi isi aslinya plain text
        $file = UploadedFile::fake()->create('pernyataan.pdf', 10, 'text/plain');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-INV-3: MIME Spoofing (Ekstensi PDF tapi Isi TXT)', $payload, 'REJECT', 'pernyataan.pdf', 'pdf', 'text/plain', '10 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_inv_4_file_pernyataan_over_limit()
    {
        $file = UploadedFile::fake()->create('pernyataan.pdf', 2049, 'application/pdf');
        $payload = $this->getBasePayload(['pernyataan_pelapor' => $file]);

        $this->runScenario('S-INV-4: Berkas Pernyataan Sedikit di Atas Batas (2049 KB)', $payload, 'REJECT', 'pernyataan.pdf', 'pdf', 'application/pdf', '2049 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_inv_5_nama_pelapor_kosong()
    {
        $payload = $this->getBasePayload(['nama_pelapor' => '']);

        $this->runScenario('S-INV-5: Nama Pelapor Kosong (Required)', $payload, 'REJECT', 'pernyataan.pdf', 'pdf', 'application/pdf', '100 KB', 0);
    }

    /** @test */
    public function test_scenario_inv_6_nama_pelapor_over_limit()
    {
        $payload = $this->getBasePayload(['nama_pelapor' => str_repeat('X', 256)]);

        $this->runScenario('S-INV-6: Nama Pelapor Melebihi Batas (256 Karakter)', $payload, 'REJECT', 'pernyataan.pdf', 'pdf', 'application/pdf', '100 KB', 256);
    }

    /** @test */
    public function test_scenario_inv_7_file_pernyataan_kosong()
    {
        $payload = $this->getBasePayload();
        unset($payload['pernyataan_pelapor']); // Hapus file pernyataan wajib

        $this->runScenario('S-INV-7: Berkas Pernyataan Wajib Tidak Diberikan', $payload, 'REJECT', null, null, null, null, strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_inv_8_email_pelapor_invalid()
    {
        $payload = $this->getBasePayload(['email_pelapor' => 'alamat_email_salah.com']);

        $this->runScenario('S-INV-8: Email Pelapor Format Tidak Valid', $payload, 'REJECT', 'pernyataan.pdf', 'pdf', 'application/pdf', '100 KB', strlen($payload['nama_pelapor']));
    }

    /** @test */
    public function test_scenario_inv_9_rsa_key_hilang()
    {
        $payload = $this->getBasePayload();

        // Pindahkan sementara kunci RSA agar terjadi exception enkripsi asimetrik
        $pubKeyPath = storage_path('app/public_key.pem');
        $tempPubKeyPath = storage_path('app/public_key.pem.bak');
        if (file_exists($pubKeyPath)) {
            rename($pubKeyPath, $tempPubKeyPath);
        }

        try {
            $this->runScenario('S-INV-9: Kunci Asimetris RSA Hilang (Enkripsi Gagal)', $payload, 'REJECT', 'pernyataan.pdf', 'pdf', 'application/pdf', '100 KB', strlen($payload['nama_pelapor']));
        } finally {
            // Pulihkan kunci RSA
            if (file_exists($tempPubKeyPath)) {
                rename($tempPubKeyPath, $pubKeyPath);
            }
        }
    }
}
