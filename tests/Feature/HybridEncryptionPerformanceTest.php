<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Helpers\AesHelper;
use App\Helpers\RsaHelper;
use Illuminate\Support\Facades\File;

class HybridEncryptionPerformanceTest extends TestCase
{
    /** @test */
    public function test_hybrid_encryption_performance()
    {
        // 1. Membaca path file dari environment variable dan jenis pengujian
        $filePath = getenv('TEST_FILE');
        $testMode = getenv('TEST_MODE') ?: 'normal';
        
        if (!$filePath) {
            $this->markTestSkipped("Variabel lingkungan 'TEST_FILE' tidak diatur. Pengujian performa dilewati.");
            return;
        }

        // NEGATIVE TEST 1: Simulasi File Tidak Ditemukan
        if ($testMode === 'file_not_found') {
            $filePath = $filePath . '_non_existent_file';
        }

        // 2. Validasi keberadaan file fisik (mendukung absolute path dan relative path terhadap base_path)
        if (!file_exists($filePath)) {
            $resolvedPath = base_path($filePath);
            if (file_exists($resolvedPath)) {
                $filePath = $resolvedPath;
            } else {
                $this->logFailureAndAssert(
                    $filePath,
                    "File tidak ditemukan di path: " . $filePath,
                    "N/A",
                    0
                );
                return;
            }
        }

        $fileName = basename($filePath);
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileSizeInBytes = filesize($filePath);
        $fileSizeInMb = round($fileSizeInBytes / (1024 * 1024), 2) . ' MB';
        $originalHash = hash_file('sha256', $filePath);
        $mimeType = mime_content_type($filePath);
        
        // Membaca isi teks dari PDF secara otomatis jika file adalah PDF dan parser tersedia
        $dummyText = '';
        if ($fileExtension === 'pdf') {
            try {
                if (class_exists(\Smalot\PdfParser\Parser::class)) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    $extractedText = $pdf->getText();
                    
                    // Bersihkan spasi berlebih
                    $extractedText = trim(preg_replace('/\s+/', ' ', $extractedText));
                    
                    if (strlen($extractedText) > 0) {
                        if (getenv('TEST_CHAR_COUNT')) {
                            $charCount = (int)getenv('TEST_CHAR_COUNT');
                            $dummyText = mb_substr($extractedText, 0, $charCount);
                        } else {
                            $dummyText = $extractedText;
                            $charCount = mb_strlen($dummyText);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Jika gagal parse, biarkan fallback jalan
            }
        }

        // Fallback jika file bukan PDF, pdf kosong, atau gagal diparse: gunakan paragraf aduan kekerasan realistis
        if (empty($dummyText)) {
            $charCount = getenv('TEST_CHAR_COUNT') ? (int)getenv('TEST_CHAR_COUNT') : 100;
            $realisticTemplate = "Saya ingin melaporkan kejadian tindakan kekerasan fisik dan verbal yang saya alami di lingkungan kampus dekat gedung rektorat pada hari Senin kemarin. Terlapor secara sengaja melakukan tindakan intimidasi serta mengucapkan kata-kata kasar yang tidak pantas di depan umum, serta mendorong saya hingga terjatuh yang mengakibatkan cedera fisik pada bagian lengan kanan saya.";
            
            if ($charCount > mb_strlen($realisticTemplate)) {
                $dummyText = str_pad($realisticTemplate, $charCount, " ");
            } else {
                $dummyText = mb_substr($realisticTemplate, 0, $charCount);
            }
        } else {
            $charCount = mb_strlen($dummyText);
        }

        // Inisialisasi variabel status dan waktu
        $encryptionStatus = 'FAIL';
        $decryptionStatus = 'FAIL';
        $integrityStatus = 'FAIL';
        $encryptionTime = 0;
        $decryptionTime = 0;
        $decryptedHash = 'N/A';
        $errorMessage = null;

        $aesKey = null;
        $encryptedAesKey = null;
        $encryptedText = null;
        $encryptedFileContent = null;
        $decryptedAesKey = null;
        $decryptedText = null;
        $decryptedFileContent = null;

        // ==========================================
        // PROSES 1: ENKRIPSI HIBRIDA
        // ==========================================
        $startEnc = microtime(true);
        try {
            // A. Generate AES-256 Key
            $aesKey = AesHelper::generateKey();
            if (!$aesKey) {
                throw new \Exception("Gagal melakukan generate kunci AES.");
            }

            // B. Encrypt AES Key using RSA-2048 Public Key
            $encryptedAesKey = RsaHelper::encryptKey($aesKey);
            if (!$encryptedAesKey) {
                throw new \Exception("Gagal mengenkripsi kunci AES dengan RSA.");
            }

            // C. Encrypt Text Aduan using AES-256
            $encryptedText = AesHelper::encryptWithKey($dummyText, $aesKey);

            // D. Encrypt File Content using AES-256
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                throw new \Exception("Gagal membaca file fisik.");
            }
            $encryptedFileContent = AesHelper::encryptWithKey($fileContent, $aesKey);
            if (!$encryptedFileContent) {
                throw new \Exception("Gagal mengenkripsi file dengan AES.");
            }

            $encryptionTime = microtime(true) - $startEnc;
            $encryptionStatus = 'PASS';
        } catch (\Exception $e) {
            $encryptionTime = microtime(true) - $startEnc;
            $encryptionStatus = 'FAIL';
            $errorMessage = "Enkripsi Gagal: " . $e->getMessage();
            
            $no = $this->logResultToCsv([
                'fileName' => $fileName,
                'format' => strtoupper($fileExtension),
                'mimeType' => $mimeType,
                'size' => $fileSizeInMb,
                'charCount' => $charCount,
                'encStatus' => $encryptionStatus,
                'decStatus' => 'N/A',
                'encTime' => $encryptionTime,
                'decTime' => 0,
                'origHash' => $originalHash,
                'decHash' => 'N/A',
                'integrity' => 'FAIL',
                'error' => $errorMessage
            ]);

            $this->printTerminalSummary(
                $no,
                $fileName,
                $fileExtension,
                $fileSizeInMb,
                $charCount,
                $encryptionStatus,
                'N/A',
                'FAIL',
                $encryptionTime,
                0,
                'FAIL',
                $errorMessage
            );

            if ($testMode === 'normal') {
                $this->fail($errorMessage);
            } else {
                $this->assertTrue(true);
            }
            return;
        }

        // ==========================================
        // SIMULASI NEGATIVE TEST (SEBELUM DEKRIPSI)
        // ==========================================
        // NEGATIVE TEST 3: Perusakan Kunci AES yang terenkripsi RSA
        if ($testMode === 'corrupt_aes_key') {
            $encryptedAesKey = substr_replace($encryptedAesKey, 'X', -5, 1);
        }

        // NEGATIVE TEST 4: Perusakan Ciphertext (AES data / file terenkripsi)
        if ($testMode === 'corrupt_ciphertext') {
            if ($encryptedFileContent) {
                $encryptedFileContent = substr_replace($encryptedFileContent, 'X', -5, 1);
            }
            if ($encryptedText) {
                $encryptedText = substr_replace($encryptedText, 'X', -5, 1);
            }
        }

        // ==========================================
        // PROSES 2: DEKRIPSI HIBRIDA
        // ==========================================
        $startDec = microtime(true);
        try {
            // A. Decrypt AES Key using RSA-2048 Private Key
            // NEGATIVE TEST 2: Kunci Key tidak valid (bukan kunci AES yang benar)
            if ($testMode === 'invalid_key') {
                $decryptedAesKey = 'wrong_aes_key_123456789012345678';
            } else {
                $decryptedAesKey = RsaHelper::decryptKey($encryptedAesKey);
            }

            if (!$decryptedAesKey || $decryptedAesKey !== $aesKey) {
                throw new \Exception("Gagal atau kunci AES hasil dekripsi RSA tidak cocok.");
            }

            // B. Decrypt Text Aduan using AES-256
            $decryptedText = AesHelper::decryptWithKey($encryptedText, $decryptedAesKey);
            if ($decryptedText !== $dummyText) {
                throw new \Exception("Teks terdekripsi tidak cocok dengan teks asli.");
            }

            // C. Decrypt File Content using AES-256
            $decryptedFileContent = AesHelper::decryptWithKey($encryptedFileContent, $decryptedAesKey);
            if ($decryptedFileContent === false) {
                throw new \Exception("Gagal mendekripsi isi file dengan AES.");
            }

            $decryptionTime = microtime(true) - $startDec;
            $decryptionStatus = 'PASS';
        } catch (\Exception $e) {
            $decryptionTime = microtime(true) - $startDec;
            $decryptionStatus = 'FAIL';
            $errorMessage = "Dekripsi Gagal: " . $e->getMessage();

            $no = $this->logResultToCsv([
                'fileName' => $fileName,
                'format' => strtoupper($fileExtension),
                'mimeType' => $mimeType,
                'size' => $fileSizeInMb,
                'charCount' => $charCount,
                'encStatus' => $encryptionStatus,
                'decStatus' => $decryptionStatus,
                'encTime' => $encryptionTime,
                'decTime' => $decryptionTime,
                'origHash' => $originalHash,
                'decHash' => 'N/A',
                'integrity' => 'FAIL',
                'error' => $errorMessage
            ]);

            $this->printTerminalSummary(
                $no,
                $fileName,
                $fileExtension,
                $fileSizeInMb,
                $charCount,
                $encryptionStatus,
                $decryptionStatus,
                'FAIL',
                $encryptionTime,
                $decryptionTime,
                'FAIL',
                $errorMessage
            );

            if ($testMode === 'normal') {
                $this->fail($errorMessage);
            } else {
                $this->assertTrue(true);
            }
            return;
        }

        // ==========================================
        // PROSES 3: VERIFIKASI INTEGRITAS (CHECKSUM)
        // ==========================================
        // NEGATIVE TEST 5: Simulasi perusakan data setelah didekripsi (Hash mismatch)
        if ($testMode === 'hash_mismatch') {
            $decryptedFileContent .= 'tampered_data';
        }

        $decryptedHash = hash('sha256', $decryptedFileContent);
        if ($originalHash === $decryptedHash) {
            $integrityStatus = 'PASS';
        } else {
            $integrityStatus = 'FAIL';
            $errorMessage = "Integritas Gagal: Hash file terdekripsi tidak sama dengan file asli.";
        }

        // ==========================================
        // PENCATATAN KE CSV & DISPLAY
        // ==========================================
        $no = $this->logResultToCsv([
            'fileName' => $fileName,
            'format' => strtoupper($fileExtension),
            'mimeType' => $mimeType,
            'size' => $fileSizeInMb,
            'charCount' => $charCount,
            'encStatus' => $encryptionStatus,
            'decStatus' => $decryptionStatus,
            'encTime' => $encryptionTime,
            'decTime' => $decryptionTime,
            'origHash' => $originalHash,
            'decHash' => $decryptedHash,
            'integrity' => $integrityStatus,
            'error' => $errorMessage ?: ''
        ]);

        $statusAkhir = ($encryptionStatus === 'PASS' && $decryptionStatus === 'PASS' && $integrityStatus === 'PASS') ? 'PASS' : 'FAIL';

        $this->printTerminalSummary(
            $no,
            $fileName,
            $fileExtension,
            $fileSizeInMb,
            $charCount,
            $encryptionStatus,
            $decryptionStatus,
            $integrityStatus,
            $encryptionTime,
            $decryptionTime,
            $statusAkhir,
            $errorMessage
        );

        if ($testMode === 'normal') {
            $this->assertEquals('PASS', $statusAkhir);
        } else {
            $this->assertEquals('FAIL', $statusAkhir);
        }
    }

    /**
     * Helper to write to CSV and return the experiment number
     */
    private function logResultToCsv(array $data)
    {
        $dirPath = storage_path('app/testing');
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $csvPath = $dirPath . '/hasil_pengujian.csv';
        $fileExists = file_exists($csvPath);

        // Jika file belum ada, tulis header 15 kolom
        if (!$fileExists) {
            $header = [
                'No',
                'Waktu Pengujian',
                'Nama File',
                'Format',
                'Ukuran File',
                'Jumlah Karakter',
                'Status Enkripsi',
                'Status Dekripsi',
                'Waktu Enkripsi (s)',
                'Waktu Dekripsi (s)',
                'Hash Asli',
                'Hash Hasil Dekripsi',
                'Integrity Check',
                'Status Akhir',
                'Error Message'
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

        $statusAkhir = ($data['encStatus'] === 'PASS' && $data['decStatus'] === 'PASS' && $data['integrity'] === 'PASS') ? 'PASS' : 'FAIL';

        $row = [
            $no,
            date('Y-m-d H:i:s'),
            $data['fileName'],
            $data['format'],
            $data['size'],
            $data['charCount'],
            $data['encStatus'],
            $data['decStatus'],
            number_format($data['encTime'], 4),
            number_format($data['decTime'], 4),
            $data['origHash'],
            $data['decHash'],
            $data['integrity'],
            $statusAkhir,
            $data['error'] ?: ''
        ];

        $file = fopen($csvPath, 'a');
        fputcsv($file, $row);
        fclose($file);

        return $no;
    }

    /**
     * Helper to log failures when files do not exist or configuration is wrong
     */
    private function logFailureAndAssert($filePath, $msg, $format, $size)
    {
        $testMode = getenv('TEST_MODE') ?: 'normal';

        $no = $this->logResultToCsv([
            'fileName' => basename($filePath) ?: 'N/A',
            'format' => $format,
            'mimeType' => 'N/A',
            'size' => $size,
            'charCount' => 0,
            'encStatus' => 'FAIL',
            'decStatus' => 'N/A',
            'encTime' => 0,
            'decTime' => 0,
            'origHash' => 'N/A',
            'decHash' => 'N/A',
            'integrity' => 'FAIL',
            'error' => $msg
        ]);

        $this->printTerminalSummary(
            $no,
            basename($filePath) ?: 'N/A',
            $format,
            $size,
            0,
            'FAIL',
            'N/A',
            'FAIL',
            0,
            0,
            'FAIL',
            $msg
        );

        if ($testMode === 'normal') {
            $this->fail($msg);
        } else {
            $this->assertTrue(true); // Memastikan PHPUnit menganggap tes hijau karena kegagalan memang diharapkan
        }
    }

    /**
     * Helper to print formatted box summary to terminal
     */
    private function printTerminalSummary($no, $fileName, $format, $size, $charCount, $enc, $dec, $integ, $encTime, $decTime, $status, $error)
    {
        // Hitung Summary Akumulatif dari CSV
        $csvPath = storage_path('app/testing/hasil_pengujian.csv');
        $totalPercobaan = 0;
        $passedCount = 0;
        $failedCount = 0;
        $totalEncTime = 0;
        $totalDecTime = 0;
        
        if (file_exists($csvPath)) {
            if (($handle = fopen($csvPath, "r")) !== false) {
                // Lewati baris pertama (header)
                fgetcsv($handle);
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 15) continue;
                    
                    $totalPercobaan++;
                    $rowStatus = $row[13]; // Test Result
                    
                    if ($rowStatus === 'PASS') {
                        $passedCount++;
                    } else {
                        $failedCount++;
                    }
                    
                    $totalEncTime += (double)$row[8]; // Waktu Enkripsi
                    $totalDecTime += (double)$row[9]; // Waktu Dekripsi
                }
                fclose($handle);
            }
        }
        
        $successRate = $totalPercobaan > 0 ? ($passedCount / $totalPercobaan) * 100 : 0;
        $avgEncTime = $totalPercobaan > 0 ? $totalEncTime / $totalPercobaan : 0;
        $avgDecTime = $totalPercobaan > 0 ? $totalDecTime / $totalPercobaan : 0;

        $output = "\n";
        $output .= "========================================\n";
        $output .= "HASIL PENGUJIAN\n";
        $output .= "========================================\n";
        $output .= sprintf("Percobaan       : %d\n", $no);
        $output .= sprintf("File            : %s\n", $fileName);
        $output .= sprintf("Format          : %s\n", strtoupper($format));
        $output .= sprintf("Ukuran          : %s\n", $size);
        $output .= sprintf("Karakter Aduan  : %d\n\n", $charCount);
        $output .= sprintf("Enkripsi        : %s\n", $enc);
        $output .= sprintf("Dekripsi        : %s\n", $dec);
        $output .= sprintf("Integrity       : %s\n\n", $integ);
        $output .= sprintf("Waktu Enkripsi  : %.4f s\n", $encTime);
        $output .= sprintf("Waktu Dekripsi  : %.4f s\n\n", $decTime);
        $output .= sprintf("Status Akhir    : %s\n", $status);
        if ($status === 'FAIL' && $error) {
            $output .= sprintf("Pesan Error     : %s\n", $error);
        }
        $output .= "========================================\n";
        $output .= "SUMMARY TOTAL PENGUJIAN (KUMULATIF)\n";
        $output .= "========================================\n";
        $output .= sprintf("Total Percobaan : %d\n", $totalPercobaan);
        $output .= sprintf("Passed          : %d\n", $passedCount);
        $output .= sprintf("Failed          : %d\n", $failedCount);
        $output .= sprintf("Success Rate    : %.2f %%\n\n", $successRate);
        $output .= sprintf("Rata-rata Waktu Enkripsi : %.4f s\n", $avgEncTime);
        $output .= sprintf("Rata-rata Waktu Dekripsi : %.4f s\n\n", $avgDecTime);
        $output .= "Hasil tersimpan:\n";
        $output .= "storage/app/testing/hasil_pengujian.csv\n";
        $output .= "========================================\n";
        
        echo $output;
    }
}
