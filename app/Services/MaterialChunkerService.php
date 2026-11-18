<?php

namespace App\Services;

class MaterialChunkerService
{
    /**
     * Memproses teks materi dan mengambil porsi materi sesuai persentase cakupan.
     *
     * @param string $text Teks materi asli
     * @param int $percentage Persentase materi yang dimasukkan (1 - 100)
     * @param string $strategy 'distributed' (merata dari awal-tengah-akhir) atau 'sequential' (dari awal sampai batas %)
     * @return array
     */
    public function extractCoverage(string $text, int $percentage = 100, string $strategy = 'distributed'): array
    {
        $percentage = max(1, min(100, $percentage));
        $normalizedText = $this->normalizeText($text);

        // Pecah berdasarkan paragraf terlebih dahulu secara aman
        $rawParagraphs = @preg_split('/\R{2,}/', $normalizedText);
        if (!is_array($rawParagraphs)) {
            $rawParagraphs = explode("\n", $normalizedText);
        }

        $paragraphs = [];
        if (is_array($rawParagraphs)) {
            foreach ($rawParagraphs as $p) {
                $trimmed = trim($p);
                if (!empty($trimmed)) {
                    $paragraphs[] = $trimmed;
                }
            }
        }

        // Jika paragraf sedikit, gunakan kalimat
        if (count($paragraphs) <= 2) {
            $units = $this->splitSentences($normalizedText);
            $unitType = 'sentence';
        } else {
            $units = $paragraphs;
            $unitType = 'paragraph';
        }

        $units = is_array($units) ? array_values($units) : [];
        $totalUnits = count($units);
        $totalWordCount = str_word_count($normalizedText);

        if ($totalUnits === 0) {
            return [
                'coverage_text' => '',
                'total_words_original' => 0,
                'words_sampled' => 0,
                'target_percentage' => $percentage,
                'actual_percentage' => 0,
                'selected_units_count' => 0,
                'total_units' => 0,
                'unit_type' => $unitType,
                'selected_units' => [],
            ];
        }

        // Tentukan jumlah unit yang diambil
        $unitsToTake = max(1, (int) round(($percentage / 100) * $totalUnits));
        $selectedIndices = [];

        if ($percentage >= 100 || $unitsToTake >= $totalUnits) {
            $selectedIndices = range(0, $totalUnits - 1);
        } elseif ($strategy === 'distributed' && $unitsToTake > 1) {
            $step = ($totalUnits - 1) / ($unitsToTake - 1);
            for ($i = 0; $i < $unitsToTake; $i++) {
                $idx = (int) round($i * $step);
                $selectedIndices[] = min($totalUnits - 1, $idx);
            }
            $selectedIndices = array_values(array_unique($selectedIndices));
        } else {
            $selectedIndices = range(0, $unitsToTake - 1);
        }

        $selectedUnits = [];
        if (is_array($selectedIndices)) {
            foreach ($selectedIndices as $idx) {
                if (isset($units[$idx])) {
                    $selectedUnits[] = $units[$idx];
                }
            }
        }

        $coverageText = implode("\n\n", $selectedUnits);
        $sampledWordCount = str_word_count($coverageText);
        $actualPercentage = $totalWordCount > 0 ? round(($sampledWordCount / $totalWordCount) * 100, 1) : 0;

        return [
            'coverage_text' => $coverageText,
            'total_words_original' => $totalWordCount,
            'words_sampled' => $sampledWordCount,
            'target_percentage' => $percentage,
            'actual_percentage' => $actualPercentage,
            'selected_units_count' => count($selectedUnits),
            'total_units' => $totalUnits,
            'unit_type' => $unitType,
            'selected_units' => $selectedUnits,
        ];
    }

    /**
     * Sanitasi teks agar valid UTF-8 dan terbebas dari byte rusak / karakter kontrol binary
     */
    public function sanitizeUtf8(string $text): string
    {
        // 1. Bersihkan invalid byte sequences dengan iconv / mb_convert
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($clean === false || empty($clean)) {
            $clean = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        // 2. Hapus karakter kontrol biner yang sering terbawa dari PDF/DOC
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean ?? $text);

        // 3. Hapus artefak parser PDF/PPT seperti kurung siku kosong <>, hex markers, dll.
        $clean = preg_replace('/<\s*>/u', ' ', $clean ?? $text);
        $clean = preg_replace('/&lt;\s*&gt;/ui', ' ', $clean ?? $text);
        $clean = preg_replace('/<[0-9a-fA-F]{2,6}>/u', ' ', $clean ?? $text);
        $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $clean ?? $text);

        return $clean ?? $text;
    }

    /**
     * Normalisasi teks input
     */
    public function normalizeText(string $text): string
    {
        $text = $this->sanitizeUtf8($text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Memecah teks menjadi kalimat dengan proteksi kebal error regex
     */
    public function splitSentences(string $text): array
    {
        $text = $this->sanitizeUtf8($text);

        // Bersihkan header pemisah antar berkas/dokumen materi
        $cleanedText = preg_replace('/^={3,}[^=\n]+={3,}$/m', '', $text) ?? $text;
        $cleanedText = preg_replace('/^-{3,}[^-\n]+-{3,}$/m', '', $cleanedText) ?? $cleanedText;
        $cleanedText = preg_replace('/\R{2,}/', "\n\n", $cleanedText) ?? $cleanedText;

        // Proteksi singkatan umum agar tidak terpecah
        $placeholders = [
            'dsb.' => '___DSB___',
            'dst.' => '___DST___',
            'dll.' => '___DLL___',
            'sdr.' => '___SDR___',
            'dr.'  => '___DRR___',
            'prof.' => '___PRF___',
            'no.'  => '___NUM___',
            'hal.' => '___HAL___',
            'vs.'  => '___VSS___',
            'pt.'  => '___PTT___',
            'cv.'  => '___CVV___',
        ];

        $safeText = str_ireplace(array_keys($placeholders), array_values($placeholders), $cleanedText);

        // Coba split dengan regex Unicode
        $parts = @preg_split('/(?<=[.!?\n])\s+(?=[A-Z0-9"\'\(\[])/u', $safeText, -1, PREG_SPLIT_NO_EMPTY);

        // Fallback jika regex Unicode gagal (misal jika ada karakter khusus non-standar)
        if (!is_array($parts) || empty($parts)) {
            $parts = @preg_split('/(?<=[.!?\n])\s+/', $safeText, -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($parts) || empty($parts)) {
                $parts = explode("\n", $safeText);
            }
        }

        // Pastikan $parts SELALU berformat array
        $parts = is_array($parts) ? $parts : [];

        $sentences = [];
        foreach ($parts as $part) {
            $restored = str_ireplace(array_values($placeholders), array_keys($placeholders), $part);
            $trimmed = trim(preg_replace('/^={2,}|={2,}$/', '', trim($restored)));
            
            // Saring dan abaikan baris judul dokumen, cover, header halaman, atau metadata PDF
            if ($this->isDocumentHeaderOrMetadata($trimmed)) {
                continue;
            }

            if (mb_strlen($trimmed) > 15 && !preg_match('/^={3,}/', $trimmed)) {
                $sentences[] = $trimmed;
            }
        }

        // Fallback jika kalimat terlalu sedikit
        if (empty($sentences)) {
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                $t = trim($line);
                if (!$this->isDocumentHeaderOrMetadata($t) && mb_strlen($t) > 15) {
                    $sentences[] = $t;
                }
            }
        }

        return array_values($sentences);
    }

    /**
     * Mendeteksi apakah suatu baris merupakan judul halaman, cover, header/footer, atau metadata dokumen PDF
     */
    public function isDocumentHeaderOrMetadata(string $line): bool
    {
        $line = trim($line);
        if (empty($line)) {
            return true;
        }

        // 1. Spasi renggang antar-huruf kapital judul (misal: "M O D U L  P Y T H O N" atau "M I N G G U")
        if (preg_match('/(?:[A-Z]\s+){2,}[A-Z]/u', $line)) {
            return true;
        }

        // 2. Baris judul cover, modul, bab, panduan, silabus, dll. (baik di awal maupun di dalam kalimat judul cover)
        if (preg_match('/\b(modul\s+python|panduan\s+praktis\s+mengajar|target\s*:\s*pemula|durasi\s*:|pertemuan\s+minggu\s+ke|fondasi\s+dasar.*logika\s+percabangan)\b/ui', $line)) {
            return true;
        }

        if (preg_match('/^(modul|bab\s+[0-9ivx]+|unit\s+[0-9]+|pertemuan\s+[0-9]+|minggu\s+[0-9]+|target\s*:|durasi\s*:|panduan\s+praktis|daftar\s+isi|halaman\s+[0-9]+|page\s+[0-9]+)\b/ui', $line)) {
            return true;
        }

        // 3. Metadata berisi bullet separator jamak (misal: "Target: Pemula • Durasi: Pertemuan Minggu Ke-1 • Panduan ...")
        if (substr_count($line, '•') >= 1 || substr_count($line, '|') >= 2 || substr_count($line, '—') >= 2) {
            if (preg_match('/(durasi|minggu|target|pertemuan|panduan|semester|kurikulum|kelas)/ui', $line)) {
                return true;
            }
        }

        // 4. Baris sangat pendek yang berformat judul kapital tanpa tanda titik di akhir
        if (mb_strlen($line) < 60 && !preg_match('/[.!?]$/u', $line) && preg_match('/^(MODUL|BAB|PENGENALAN|RINGKASAN|DAFTAR|SILABUS|PRAKTIKUM|PETUNJUK)\b/ui', $line)) {
            return true;
        }

        return false;
    }
}
