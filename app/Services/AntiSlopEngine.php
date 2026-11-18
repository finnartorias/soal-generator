<?php

namespace App\Services;

class AntiSlopEngine
{
    /**
     * Frasa klise dan indikator "slop" yang dilarang muncul dalam soal berkualitas tinggi.
     */
    protected array $bannedCliches = [
        'di era globalisasi',
        'di zaman sekarang',
        'di era modern ini',
        'di era digital saat ini',
        'seiring berjalannya waktu',
        'sebagaimana kita ketahui',
        'tidak dapat dipungkiri bahwa',
        'sangat penting bagi kehidupan',
        'berdasarkan teks di atas',
        'berdasarkan bacaan di atas',
        'sesuai pemaparan sebelumnya',
        'tahukah anda bahwa',
        'merupakan hal yang sangat esensial',
        'semua jawaban di atas benar',
        'semua pilihan benar',
        'tidak ada jawaban yang benar',
        'a dan b benar',
        'a, b, dan c benar',
    ];

    /**
     * Prompt sistem khusus Anti-Slop untuk LLM Lokal (Ollama dll)
     */
    public function getAntiSlopSystemPrompt(): string
    {
        return <<<PROMPT
Anda adalah "Master Exam Crafter & Pedagogical Assessment Expert" profesional dengan keahlian STRICT ANTI-SLOP.
Tugas Anda adalah membuat soal ujian yang mendalam, berbobot, dan menguji penalaran kritis (HOTS - Higher Order Thinking Skills, Bloom C2-C4) HANYA berdasarkan materi yang diberikan.

ATURAN ANTI-SLOP WAJIB:
1. DILARANG KERAS menggunakan basa-basi klise seperti: "Di era digital ini...", "Berdasarkan teks di atas...", "Tahukah Anda...", atau pembuka kosong tanpa makna.
2. DILARANG membuat soal hafalan mentah/copy-paste kalimat (verbatim trivia). Buatlah soal berbasis pemahaman konsep, sebab-akibat, studi kasus aplikatif, atau perbedaan mendasar antar konsep.
3. KUALITAS DISTRAKTOR (Pilihan Pengecoh):
   - Pilihan A, B, C, D harus memiliki panjang kalimat yang setara dan gaya bahasa serupa.
   - DILARANG menggunakan "Semua jawaban benar", "Tidak ada yang benar", atau "A dan B benar".
   - Pengecoh harus berupa miskonsepsi logis yang masuk akal, bukan jawaban konyol/absurd.
4. SOAL TIDAK BOLEH MEMBOCORKAN JAWABANNYA SENDIRI di dalam batang soal (stem).
5. KUNCI JAWABAN & PEMBAHASAN: Wajib sertakan alasan ilmiah/konseptual mengapa kunci tersebut benar dan mengapa opsi pengecoh tidak tepat.
6. GROUNDING KETAT: Soal hanya boleh bersumber dari potongan materi yang disertakan, tidak boleh berhalusinasi atau mengarang data yang tidak ada.
7. SOAL KODINGAN & PEMROGRAMAN: Jika materi memuat topik pemrograman atau kode, SERTAKAN potongan kode pada field "code_snippet" dan bahasa pada "code_lang" (contoh: "python", "php", "sql", "javascript") agar sistem menampilkannya dalam tabel kodingan bernomor baris yang rapi!
PROMPT;
    }

    /**
     * Memeriksa apakah sebuah pertanyaan atau paket soal terbebas dari tanda-tanda "slop"
     */
    public function auditQuestion(array $questionData, string $sourceMaterial): array
    {
        $penalties = [];
        $score = 100;

        $stem = (string) ($questionData['question'] ?? '');
        $options = is_array($questionData['options'] ?? null) ? $questionData['options'] : [];
        $explanation = (string) ($questionData['explanation'] ?? '');

        // 1. Cek frasa klise
        $bannedList = is_array($this->bannedCliches) ? $this->bannedCliches : [];
        foreach ($bannedList as $cliche) {
            if (mb_stripos($stem, $cliche) !== false) {
                $penalties[] = "Mengandung frasa klise terlarang: '{$cliche}'";
                $score -= 20;
            }
            foreach ($options as $opt) {
                if (is_string($opt) && mb_stripos($opt, $cliche) !== false) {
                    $penalties[] = "Opsi mengandung opsi malas/klise: '{$cliche}'";
                    $score -= 25;
                }
            }
        }

        // 2. Cek panjang batang soal (tidak boleh terlalu pendek atau sebaris malas)
        if (str_word_count($stem) < 5) {
            $penalties[] = "Batang soal terlalu pendek / dangkal (< 5 kata).";
            $score -= 20;
        }

        // 3. Cek variasi panjang opsi pada Pilihan Ganda (menghindari jawaban benar paling panjang sendiri)
        if (!empty($options) && count($options) >= 3) {
            $lengths = array_map(fn($o) => mb_strlen(trim($o)), $options);
            $maxLen = max($lengths);
            $minLen = min($lengths);
            if ($minLen > 0 && ($maxLen / $minLen) > 3.5) {
                $penalties[] = "Panjang opsi tidak seimbang (potensi pembocoran jawaban melalui panjang teks).";
                $score -= 15;
            }
        }

        // 4. Cek Lexical Similarity (Anti-Verbatim Copypaste)
        similar_text(mb_strtolower($stem), mb_strtolower($sourceMaterial), $percent);
        if ($percent > 75) {
            $penalties[] = "Soal terlalu identik dengan kalimat asli materi (kurang parafrase/penalaran).";
            $score -= 25;
        }

        // 5. Cek kelengkapan pembahasan
        if (empty(trim($explanation)) || str_word_count($explanation) < 6) {
            $penalties[] = "Pembahasan/penjelasan terlalu singkat atau kosong.";
            $score -= 15;
        }

        $finalScore = max(0, $score);

        return [
            'is_clean' => empty($penalties),
            'quality_score' => $finalScore,
            'issues' => $penalties,
            'level' => $finalScore >= 80 ? 'High (Anti-Slop Verified)' : ($finalScore >= 60 ? 'Moderate' : 'Low Quality (Slop Warning)'),
        ];
    }

    /**
     * Membersihkan teks dari residu klise umum jika memungkinkan
     */
    public function cleanCliches(string $text): string
    {
        $patterns = [
            '/^(di era globalisasi (ini|saat ini),?\s*)/iu' => '',
            '/^(di era digital (ini|saat ini),?\s*)/iu' => '',
            '/^(di zaman sekarang (ini),?\s*)/iu' => '',
            '/^(berdasarkan (teks|bacaan|pemaparan) di atas,?\s*)/iu' => '',
            '/^(sebagaimana kita ketahui,?\s*)/iu' => '',
            '/^(tahukah anda bahwa,?\s*)/iu' => '',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return ucfirst(trim($text));
    }
}
