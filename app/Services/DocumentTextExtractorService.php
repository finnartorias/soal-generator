<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentTextExtractorService
{
    protected string $ollamaBaseUrl;

    public function __construct()
    {
        $this->ollamaBaseUrl = env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434');
    }

    /**
     * Mengekstrak teks dari berbagai jenis berkas yang diunggah
     *
     * @param UploadedFile|string $file Berkas upload atau path file
     * @return array [ 'text' => string, 'format' => string, 'details' => string ]
     */
    public function extractText($file): array
    {
        $path = is_string($file) ? $file : $file->getRealPath();
        $originalName = is_string($file) ? basename($file) : $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Berkas tidak ditemukan: {$path}");
        }

        $result = match ($extension) {
            'pdf' => $this->extractFromPdf($path),
            'docx' => $this->extractFromDocx($path),
            'doc' => $this->extractFromLegacyDoc($path),
            'pptx' => $this->extractFromPptx($path),
            'ppt' => $this->extractFromLegacyPpt($path),
            'xlsx' => $this->extractFromXlsx($path),
            'xls' => $this->extractFromLegacyXls($path),
            'csv' => $this->extractFromCsv($path),
            'zip' => $this->extractFromZipArchive($path),
            'rar' => $this->extractFromRarArchive($path),
            'png', 'jpg', 'jpeg', 'webp', 'bmp' => $this->extractFromImage($path, $extension),
            'txt', 'md', 'text', 'rtf', 'json' => [
                'text' => file_get_contents($path),
                'format' => strtoupper($extension),
                'details' => "Teks mentah ({$originalName})",
            ],
            default => $this->fallbackStreamExtract($path, $extension),
        };

        // Sanitasi teks hasil ekstraksi agar terjamin valid UTF-8 dan bebas dari artefak PDF/PPT (<>, dll)
        $rawText = $result['text'] ?? '';
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $rawText);
        if ($clean === false || empty($clean)) {
            $clean = mb_convert_encoding($rawText, 'UTF-8', 'UTF-8');
        }
        $noControls = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean ?? $rawText);
        $result['text'] = $this->cleanArtifacts($noControls ?? '');

        return $result;
    }

    /**
     * Membersihkan artefak teks umum dari parser PDF/PPT seperti kurung siku kosong <>, hex codes, dll.
     */
    public function cleanArtifacts(string $text): string
    {
        // 1. Bersihkan tanda kurung siku kosong <> atau < > yang sering dihasilkan parser PDF saat membaca spasi/kerning
        $text = preg_replace('/<\s*>/u', ' ', $text) ?? $text;
        $text = preg_replace('/&lt;\s*&gt;/ui', ' ', $text) ?? $text;
        $text = preg_replace('/<[0-9a-fA-F]{2,6}>/u', ' ', $text) ?? $text;

        // 2. Bersihkan karakter zero-width dan byte tak terlihat (BOM, zero-width space, dll)
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;

        // 3. Rapikan spasi berlebih dan spasi sebelum tanda baca
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+([.,!?:;])/', '$1', $text) ?? $text;

        return trim($text);
    }

    /**
     * Ekstraksi teks dari PDF menggunakan Smalot PdfParser
     */
    protected function extractFromPdf(string $path): array
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
            $pages = count($pdf->getPages());

            return [
                'text' => trim($text),
                'format' => 'PDF',
                'details' => "Dokumen PDF ({$pages} halaman)",
            ];
        } catch (\Throwable $e) {
            // Fallback binary text extraction jika PDF terproteksi / parsing error
            return $this->fallbackStreamExtract($path, 'pdf');
        }
    }

    /**
     * Ekstraksi teks dari berkas DOCX (Word Modern)
     */
    protected function extractFromDocx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml) {
                // Konversi XML menjadi teks berparagraf
                $cleanXml = str_replace(['</w:p>', '</w:tr>'], ["\n\n", "\n"], $xml);
                $text = strip_tags($cleanXml);
                $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                $text = preg_replace('/[ \t]+/', ' ', $text);

                return [
                    'text' => trim($text),
                    'format' => 'DOCX',
                    'details' => 'Dokumen Microsoft Word (.docx)',
                ];
            }
        }

        return $this->fallbackStreamExtract($path, 'docx');
    }

    /**
     * Ekstraksi teks dari berkas PPTX (PowerPoint Modern)
     */
    protected function extractFromPptx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $slideTexts = [];
            $slideNum = 1;

            // Baca semua slide
            while (($xml = $zip->getFromName("ppt/slides/slide{$slideNum}.xml")) !== false) {
                $cleanXml = str_replace(['</a:p>', '</a:tr>'], ["\n", " "], $xml);
                $content = trim(strip_tags($cleanXml));
                if (!empty($content)) {
                    $slideTexts[] = "[SLIDE {$slideNum}]\n" . html_entity_decode($content, ENT_QUOTES, 'UTF-8');
                }
                $slideNum++;
            }

            $zip->close();

            if (!empty($slideTexts)) {
                return [
                    'text' => implode("\n\n", $slideTexts),
                    'format' => 'PPTX',
                    'details' => 'Presentasi PowerPoint (' . count($slideTexts) . ' slide terdeteksi)',
                ];
            }
        }

        return $this->fallbackStreamExtract($path, 'pptx');
    }

    /**
     * Ekstraksi teks dari berkas XLSX (Excel Modern)
     */
    protected function extractFromXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
            $zip->close();

            if ($sharedStringsXml) {
                // Ambil semua string dari sharedStrings
                preg_match_all('/<t[^>]*>(.*?)<\/t>/su', $sharedStringsXml, $matches);
                if (!empty($matches[1])) {
                    $cells = array_map(fn($c) => html_entity_decode(strip_tags($c), ENT_QUOTES, 'UTF-8'), $matches[1]);
                    return [
                        'text' => implode("\n", array_filter(array_map('trim', $cells))),
                        'format' => 'XLSX',
                        'details' => 'Spreadsheet Excel (' . count($cells) . ' sel teks diekstrak)',
                    ];
                }
            }
        }

        return $this->fallbackStreamExtract($path, 'xlsx');
    }

    /**
     * Ekstraksi teks dari CSV
     */
    protected function extractFromCsv(string $path): array
    {
        $lines = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 4096, ',')) !== false) {
                $cleanRow = array_filter(array_map('trim', $row));
                if (!empty($cleanRow)) {
                    $lines[] = implode(' | ', $cleanRow);
                }
            }
            fclose($handle);
        }

        return [
            'text' => implode("\n", $lines),
            'format' => 'CSV',
            'details' => 'Data Tabel CSV (' . count($lines) . ' baris)',
        ];
    }

    /**
     * Ekstraksi dari arsip ZIP (membuka file dan membaca dokumen materi di dalamnya)
     */
    protected function extractFromZipArchive(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Gagal membuka berkas ZIP.");
        }

        $tempDir = storage_path('app/temp_zip_' . uniqid());
        mkdir($tempDir, 0777, true);
        $zip->extractTo($tempDir);
        $zip->close();

        $combinedText = $this->scanDirectoryAndExtract($tempDir);
        $this->deleteDirectory($tempDir);

        return [
            'text' => $combinedText,
            'format' => 'ZIP',
            'details' => 'Arsip ZIP (semua dokumen di dalamnya diekstrak & digabungkan)',
        ];
    }

    /**
     * Ekstraksi dari arsip RAR
     */
    protected function extractFromRarArchive(string $path): array
    {
        $tempDir = storage_path('app/temp_rar_' . uniqid());
        mkdir($tempDir, 0777, true);

        // Coba ekstrak menggunakan tar.exe bawaan Windows
        $cmd = sprintf('tar -xf %s -C %s 2>&1', escapeshellarg($path), escapeshellarg($tempDir));
        shell_exec($cmd);

        $combinedText = $this->scanDirectoryAndExtract($tempDir);
        $this->deleteDirectory($tempDir);

        if (empty(trim($combinedText))) {
            // Jika tar tidak support RAR di sistem ini, gunakan binary stream extractor
            return $this->fallbackStreamExtract($path, 'rar');
        }

        return [
            'text' => $combinedText,
            'format' => 'RAR',
            'details' => 'Arsip RAR (dokumen di dalamnya diekstrak)',
        ];
    }

    /**
     * Ekstraksi teks dari Gambar (JPG, PNG, WEBP, BMP)
     * Menggunakan Windows Native OCR Engine atau Ollama Vision Lokal
     */
    protected function extractFromImage(string $path, string $ext): array
    {
        // 1. Coba Ollama Vision jika model vision terpasang
        $visionText = $this->tryOllamaVision($path);
        if (!empty(trim($visionText))) {
            return [
                'text' => $visionText,
                'format' => 'GAMBAR (Ollama Vision)',
                'details' => "Materi diekstrak dari gambar menggunakan Local Vision LLM ({$ext})",
            ];
        }

        // 2. Coba Windows Native OCR (Built-in Windows.Media.Ocr tanpa internet!)
        $ocrText = $this->runWindowsNativeOcr($path);
        if (!empty(trim($ocrText))) {
            return [
                'text' => $ocrText,
                'format' => 'GAMBAR (Windows OCR)',
                'details' => "Teks materi diekstrak melalui Windows Native OCR ({$ext})",
            ];
        }

        throw new \RuntimeException("Tidak dapat mengekstrak teks dari gambar. Pastikan gambar memuat teks tulisan yang jelas atau jalankan model vision di Ollama.");
    }

    /**
     * Eksekusi Windows Native OCR via PowerShell
     */
    protected function runWindowsNativeOcr(string $imagePath): string
    {
        $psScript = <<<PS
Add-Type -AssemblyName System.Drawing
[Windows.Media.Ocr.OcrEngine, Windows.Foundation, ContentType = WindowsRuntime] | Out-Null
[Windows.Graphics.Imaging.BitmapDecoder, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
[Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime] | Out-Null

try {
    \$absPath = Resolve-Path "$imagePath"
    \$asyncOp = [Windows.Storage.StorageFile]::GetFileFromPathAsync(\$absPath)
    while (\$asyncOp.Status -eq 'Started') { Start-Sleep -Milliseconds 20 }
    \$file = \$asyncOp.GetResults()

    \$streamOp = \$file.OpenAsync([Windows.Storage.FileAccessMode]::Read)
    while (\$streamOp.Status -eq 'Started') { Start-Sleep -Milliseconds 20 }
    \$stream = \$streamOp.GetResults()

    \$decoderOp = [Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync(\$stream)
    while (\$decoderOp.Status -eq 'Started') { Start-Sleep -Milliseconds 20 }
    \$decoder = \$decoderOp.GetResults()

    \$bitmapOp = \$decoder.GetSoftwareBitmapAsync()
    while (\$bitmapOp.Status -eq 'Started') { Start-Sleep -Milliseconds 20 }
    \$bitmap = \$bitmapOp.GetResults()

    \$engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()
    if (\$engine -eq \$null) {
        \$engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromLanguage([Windows.Globalization.Language]::new("en-US"))
    }

    \$ocrOp = \$engine.RecognizeAsync(\$bitmap)
    while (\$ocrOp.Status -eq 'Started') { Start-Sleep -Milliseconds 20 }
    \$result = \$ocrOp.GetResults()

    Write-Output \$result.Text
} catch {
    Write-Output ""
}
PS;

        $tempScriptPath = storage_path('app/ocr_' . uniqid() . '.ps1');
        file_put_contents($tempScriptPath, $psScript);

        $output = shell_exec("powershell -ExecutionPolicy Bypass -File " . escapeshellarg($tempScriptPath));
        @unlink($tempScriptPath);

        return trim($output ?? '');
    }

    /**
     * Coba ekstraksi visual menggunakan model multimodal lokal Ollama (misal llama3.2-vision atau llava)
     */
    protected function tryOllamaVision(string $imagePath): string
    {
        try {
            $tagsRes = Http::timeout(2)->get("{$this->ollamaBaseUrl}/api/tags");
            if (!$tagsRes->successful()) {
                return '';
            }

            $models = $tagsRes->json()['models'] ?? [];
            $visionModel = null;
            foreach ($models as $m) {
                $name = strtolower($m['name']);
                if (str_contains($name, 'vision') || str_contains($name, 'llava') || str_contains($name, 'bakllava')) {
                    $visionModel = $m['name'];
                    break;
                }
            }

            if (!$visionModel) {
                return '';
            }

            $base64 = base64_encode(file_get_contents($imagePath));
            $res = Http::timeout(60)->post("{$this->ollamaBaseUrl}/api/generate", [
                'model' => $visionModel,
                'prompt' => 'Transkripsikan seluruh teks materi pembelajaran dan uraian konsep penting yang ada di dalam gambar ini secara lengkap dalam bahasa Indonesia.',
                'images' => [$base64],
                'stream' => false,
            ]);

            if ($res->successful()) {
                return trim($res->json()['response'] ?? '');
            }
        } catch (\Throwable $e) {
            // Ignore & fallback to native OCR
        }

        return '';
    }

    /**
     * Legacy .doc binary text extractor
     */
    protected function extractFromLegacyDoc(string $path): array
    {
        $raw = file_get_contents($path);
        // Ekstraksi blok teks unicode dan ascii yang koheren
        $lines = $this->extractReadableStrings($raw);
        return [
            'text' => implode("\n", $lines),
            'format' => 'DOC (Legacy)',
            'details' => 'Dokumen Word Format Lama (.doc)',
        ];
    }

    /**
     * Legacy .ppt binary text extractor
     */
    protected function extractFromLegacyPpt(string $path): array
    {
        $raw = file_get_contents($path);
        $lines = $this->extractReadableStrings($raw);
        return [
            'text' => implode("\n", $lines),
            'format' => 'PPT (Legacy)',
            'details' => 'Presentasi PowerPoint Lama (.ppt)',
        ];
    }

    /**
     * Legacy .xls binary text extractor
     */
    protected function extractFromLegacyXls(string $path): array
    {
        $raw = file_get_contents($path);
        $lines = $this->extractReadableStrings($raw);
        return [
            'text' => implode("\n", $lines),
            'format' => 'XLS (Legacy)',
            'details' => 'Spreadsheet Excel Lama (.xls)',
        ];
    }

    /**
     * Fallback stream text extraction untuk berkas biner
     */
    protected function fallbackStreamExtract(string $path, string $ext): array
    {
        $raw = file_get_contents($path);
        $lines = $this->extractReadableStrings($raw);
        return [
            'text' => implode("\n", $lines),
            'format' => strtoupper($ext),
            'details' => "Ekstraksi string teks dari berkas {$ext}",
        ];
    }

    /**
     * Algoritma ekstraksi string yang dapat dibaca manusia dari berkas biner
     */
    protected function extractReadableStrings(string $binary): array
    {
        // 1. Ekstraksi string UTF-8 / ASCII yang panjangnya minimal 4 karakter
        preg_match_all('/[\x20-\x7E\xA0-\xFF]{4,}/u', $binary, $asciiMatches);

        // 2. Ekstraksi string UTF-16LE umum pada OLE Microsoft
        $utf16Decoded = @iconv('UTF-16LE', 'UTF-8//IGNORE', $binary);
        preg_match_all('/[\x20-\x7E\xA0-\xFF]{4,}/u', $utf16Decoded ?: '', $utf16Matches);

        $merged = array_merge($asciiMatches[0] ?? [], $utf16Matches[0] ?? []);
        $clean = [];

        foreach ($merged as $str) {
            $trimmed = trim($str);
            // Saring kata sampah dan nama file internal
            if (mb_strlen($trimmed) >= 5 && !preg_match('/^(Font|CompObj|WordDocument|SummaryInformation|Table|DocumentSummaryInformation)$/i', $trimmed)) {
                $clean[] = $trimmed;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Memindai direktori rekursif dan mengekstrak teks dari semua dokumen di dalamnya
     */
    protected function scanDirectoryAndExtract(string $dir): string
    {
        $allTexts = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $ext = strtolower($item->getExtension());
                if (in_array($ext, ['txt', 'md', 'pdf', 'docx', 'pptx', 'xlsx', 'csv', 'doc', 'ppt', 'xls'])) {
                    try {
                        $extracted = $this->extractText($item->getPathname());
                        if (!empty(trim($extracted['text']))) {
                            $allTexts[] = "=== BERKAS: " . $item->getFilename() . " ({$extracted['format']}) ===\n" . $extracted['text'];
                        }
                    } catch (\Throwable $e) {
                        // Skip unreadable subfile
                    }
                }
            }
        }

        return implode("\n\n", $allTexts);
    }

    /**
     * Mengekstrak teks dari BANYAK berkas sekaligus tanpa batasan jumlah
     *
     * @param array $files Kumpulan file (UploadedFile atau path string)
     * @return array
     */
    public function extractMultiple(array $files): array
    {
        $combinedTexts = [];
        $filesMetadata = [];
        $totalWords = 0;
        $successCount = 0;
        $errorCount = 0;

        foreach ($files as $file) {
            $filename = is_string($file) ? basename($file) : $file->getClientOriginalName();
            $fileSize = is_string($file) ? (file_exists($file) ? filesize($file) : 0) : $file->getSize();
            $sizeKb = round($fileSize / 1024, 1);

            try {
                $result = $this->extractText($file);
                $text = trim($result['text'] ?? '');
                $words = str_word_count($text);

                if (!empty($text)) {
                    $combinedTexts[] = "=== SUMBER MATERI: {$filename} ({$result['format']}) ===\n" . $text;
                    $totalWords += $words;
                    $successCount++;

                    $filesMetadata[] = [
                        'name' => $filename,
                        'format' => $result['format'],
                        'details' => $result['details'],
                        'words' => $words,
                        'size_kb' => $sizeKb,
                        'status' => 'success',
                    ];
                } else {
                    $filesMetadata[] = [
                        'name' => $filename,
                        'format' => $result['format'] ?? 'UNKNOWN',
                        'details' => 'Teks kosong atau tidak terbaca',
                        'words' => 0,
                        'size_kb' => $sizeKb,
                        'status' => 'warning',
                    ];
                }
            } catch (\Throwable $e) {
                $errorCount++;
                $filesMetadata[] = [
                    'name' => $filename,
                    'format' => 'ERROR',
                    'details' => $e->getMessage(),
                    'words' => 0,
                    'size_kb' => $sizeKb,
                    'status' => 'error',
                ];
            }
        }

        return [
            'combined_text' => implode("\n\n" . str_repeat('=', 50) . "\n\n", $combinedTexts),
            'files_metadata' => $filesMetadata,
            'total_words' => $totalWords,
            'total_files' => count($files),
            'success_count' => $successCount,
            'error_count' => $errorCount,
        ];
    }

    /**
     * Menghapus direktori sementara beserta isinya
     */
    protected function deleteDirectory(string $dir): bool
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return @unlink($dir);

        $items = @scandir($dir);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item == '.' || $item == '..') continue;
                if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
            }
        }

        return @rmdir($dir);
    }
}
