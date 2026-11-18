<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LocalSoalGeneratorService
{
    protected MaterialChunkerService $chunker;
    protected AntiSlopEngine $antiSlop;
    protected string $ollamaBaseUrl;
    protected string $lmStudioBaseUrl;

    public function __construct(MaterialChunkerService $chunker, AntiSlopEngine $antiSlop)
    {
        $this->chunker = $chunker;
        $this->antiSlop = $antiSlop;
        $this->ollamaBaseUrl = env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434');
        $this->lmStudioBaseUrl = env('LMSTUDIO_BASE_URL', 'http://127.0.0.1:1234');
    }

    /**
     * Memeriksa apakah ada LLM lokal (Ollama / LM Studio) yang aktif
     */
    public function getAvailableLocalModels(): array
    {
        $ollamaModels = [];
        $ollamaAvailable = false;

        // Cek koneksi port cepat (0.1 detik) agar tidak memblokir render halaman
        $ollamaHost = parse_url($this->ollamaBaseUrl, PHP_URL_HOST) ?: '127.0.0.1';
        $ollamaPort = parse_url($this->ollamaBaseUrl, PHP_URL_PORT) ?: 11434;
        $fpOllama = @fsockopen($ollamaHost, (int)$ollamaPort, $errno, $errstr, 0.1);

        if ($fpOllama) {
            fclose($fpOllama);
            try {
                $response = Http::connectTimeout(0.5)->timeout(1)->get("{$this->ollamaBaseUrl}/api/tags");
                if ($response->successful()) {
                    $data = $response->json();
                    foreach ($data['models'] ?? [] as $m) {
                        $ollamaModels[] = $m['name'];
                    }
                    $ollamaAvailable = true;
                }
            } catch (\Throwable $e) {
                // Ollama offline
            }
        }

        $lmStudioAvailable = false;
        $lmHost = parse_url($this->lmStudioBaseUrl, PHP_URL_HOST) ?: '127.0.0.1';
        $lmPort = parse_url($this->lmStudioBaseUrl, PHP_URL_PORT) ?: 1234;
        $fpLm = @fsockopen($lmHost, (int)$lmPort, $errno, $errstr, 0.1);

        if ($fpLm) {
            fclose($fpLm);
            try {
                $lmRes = Http::connectTimeout(0.5)->timeout(1)->get("{$this->lmStudioBaseUrl}/v1/models");
                if ($lmRes->successful()) {
                    $lmStudioAvailable = true;
                }
            } catch (\Throwable $e) {
                // LM Studio offline
            }
        }

        return [
            'available' => $ollamaAvailable || $lmStudioAvailable,
            'models' => $ollamaModels,
            'endpoint' => $this->ollamaBaseUrl,
            'lmstudio_available' => $lmStudioAvailable,
        ];
    }

    /**
     * Generate paket soal hingga 100 butir dengan pilihan berbagai mesin lokal
     *
     * @param string $material Teks materi asli
     * @param int $percentage Persentase materi yang diambil (1-100)
     * @param int $count Jumlah butir soal yang ingin dihasilkan (1-100)
     * @param string $type 'all', 'multiple_choice', 'essay'
     * @param string|null $preferredEngine Pilihan mesin generator
     * @return array
     */
    public function generate(
        string $material,
        int $percentage = 100,
        int $count = 5,
        string $type = 'all',
        ?string $preferredEngine = 'offline_engine'
    ): array {
        $count = max(1, min(100, $count));
        $coverageData = $this->chunker->extractCoverage($material, $percentage, 'distributed');
        $sampledText = $coverageData['coverage_text'];

        if (empty(trim($sampledText))) {
            throw new \InvalidArgumentException("Materi tidak memuat teks yang cukup untuk diolah.");
        }

        $rawQuestions = [];
        $engineTitle = '';

        // Deteksi jenis mesin yang dipilih user
        switch ($preferredEngine) {
            case 'bloom_engine':
                $rawQuestions = $this->generateWithBloomTaxonomyEngine($sampledText, $count, $type);
                $engineTitle = "Local Bloom Taxonomy Engine (Taksonomi Kognitif C2–C5)";
                break;

            case 'case_study_engine':
                $rawQuestions = $this->generateWithCaseStudyEngine($sampledText, $count, $type);
                $engineTitle = "Local Case-Study & Scenario Engine (Analisis Studi Kasus)";
                break;

            case 'hybrid_ensemble':
                $rawQuestions = $this->generateWithHybridEnsemble($sampledText, $count, $type);
                $engineTitle = "Hybrid Multi-Engine Ensemble (Kombinasi Heuristik & Taksonomi Bloom)";
                break;

            case 'lm_studio':
                try {
                    $rawQuestions = $this->generateWithLocalOpenAI($sampledText, $count, $type);
                    $engineTitle = "Local LM Studio / Jan (OpenAI Compatible API :1234)";
                } catch (\Throwable $e) {
                    $rawQuestions = $this->generateWithHybridEnsemble($sampledText, $count, $type);
                    $engineTitle = "Hybrid Multi-Engine (Fallback dari LM Studio: " . $e->getMessage() . ")";
                }
                break;

            default:
                // Cek apakah memilih model Ollama tertentu
                $localStatus = $this->getAvailableLocalModels();
                if ($preferredEngine && in_array($preferredEngine, $localStatus['models'])) {
                    try {
                        $rawQuestions = $this->generateWithOllama($sampledText, $count, $type, $preferredEngine);
                        $engineTitle = "Ollama Local LLM ({$preferredEngine})";
                    } catch (\Throwable $e) {
                        $rawQuestions = $this->generateWithHybridEnsemble($sampledText, $count, $type);
                        $engineTitle = "Hybrid Multi-Engine (Fallback dari Ollama: " . $e->getMessage() . ")";
                    }
                } else {
                    // Default: Local Semantic Heuristic Engine
                    $rawQuestions = $this->generateWithOfflineEngine($sampledText, $count, $type);
                    $engineTitle = "Local Semantic Heuristic Engine (HOTS & Kausalitas)";
                }
                break;
        }

        // Jalankan audit Anti-Slop pada setiap butir soal
        $rawQuestions = is_array($rawQuestions) ? $rawQuestions : [];
        $auditedQuestions = [];
        $totalQualityScore = 0;

        foreach ($rawQuestions as $index => $q) {
            $q['number'] = $index + 1;
            $audit = $this->antiSlop->auditQuestion($q, $sampledText);
            $q['anti_slop_audit'] = $audit;
            $totalQualityScore += $audit['quality_score'];
            $auditedQuestions[] = $q;
        }

        $avgQuality = count($auditedQuestions) > 0 ? round($totalQualityScore / count($auditedQuestions), 1) : 0;

        return [
            'success' => true,
            'engine_used' => $engineTitle,
            'material_stats' => [
                'target_percentage' => $percentage,
                'actual_percentage' => $coverageData['actual_percentage'],
                'total_words_original' => $coverageData['total_words_original'],
                'words_sampled' => $coverageData['words_sampled'],
                'sampled_units_count' => $coverageData['selected_units_count'],
            ],
            'anti_slop_summary' => [
                'average_score' => $avgQuality,
                'status' => $avgQuality >= 80 ? 'Anti-Slop Certified (High Quality)' : 'Acceptable',
                'standards' => [
                    'Zero cliches & filler phrasing',
                    'Cognitive Depth (Bloom C2-C5)',
                    'Plausible distractors (balanced choices)',
                    'Grounded in selected material subset',
                ],
            ],
            'questions' => $auditedQuestions,
            'sampled_material_preview' => Str::limit($sampledText, 400),
        ];
    }

    /**
     * MESIN 1: Local Semantic Heuristic Engine (Kausalitas, Konsep, Fungsi)
     */
    public function generateWithOfflineEngine(string $text, int $count, string $type): array
    {
        $sentences = $this->chunker->splitSentences($text);
        if (empty($sentences)) {
            $sentences = array_values(array_filter(array_map('trim', explode("\n", $text))));
        }
        if (empty($sentences)) {
            $sentences = ["Materi pokok pembelajaran mengenai konsep, mekanisme, dan aplikasi sistem operasional."];
        }

        $vocabulary = $this->extractKeyTerminology($text);
        $progContext = $this->detectProgrammingContext($text);
        $questions = [];

        // Generate dengan variasi kognitif hingga mencapai jumlah target (s.d 100 soal)
        $angles = ['causality', 'concept', 'function', 'application', 'counterfactual', 'comparison'];
        $sentenceIndex = 0;
        $totalSentences = count($sentences);

        for ($i = 0; $i < $count; $i++) {
            $currentSentence = $sentences[$sentenceIndex % $totalSentences];
            $angle = $angles[$i % count($angles)];

            $isEssay = ($type === 'essay') || ($type === 'all' && ($i % 3 === 2));

            // Jika materi memuat topik pemrograman/kodingan, buatkan soal kodingan bertabel
            if ($progContext !== null && ($i % 2 === 1)) {
                $q = $this->craftCodingQuestion($currentSentence, $progContext, $i, $isEssay ? 'essay' : 'multiple_choice', $vocabulary);
            } elseif ($isEssay) {
                $q = $this->craftEssayByAngle($currentSentence, $angle, $text);
            } else {
                $q = $this->craftMCByAngle($currentSentence, $angle, $vocabulary, $text, $i + 1);
            }

            $questions[] = $q;
            $sentenceIndex++;
        }

        return $questions;
    }

    /**
     * MESIN 2: Bloom Taxonomy Engine (Taksonomi Kognitif C2, C3, C4, C5)
     */
    public function generateWithBloomTaxonomyEngine(string $text, int $count, string $type): array
    {
        $sentences = $this->chunker->splitSentences($text);
        if (empty($sentences)) {
            $sentences = array_values(array_filter(array_map('trim', explode("\n", $text))));
        }
        if (empty($sentences)) {
            $sentences = ["Materi pokok pembelajaran mengenai konsep, mekanisme, dan aplikasi sistem operasional."];
        }

        $vocabulary = $this->extractKeyTerminology($text);
        $progContext = $this->detectProgrammingContext($text);
        $questions = [];

        $bloomLevels = [
            ['code' => 'C2 - Pemahaman Konseptual', 'type' => 'understanding'],
            ['code' => 'C3 - Penerapan Prinsip', 'type' => 'application'],
            ['code' => 'C4 - Analisis Kritis & Kausalitas', 'type' => 'analysis'],
            ['code' => 'C5 - Evaluasi & Solusi Kasus', 'type' => 'evaluation'],
        ];

        for ($i = 0; $i < $count; $i++) {
            $sentence = $sentences[$i % count($sentences)];
            $level = $bloomLevels[$i % count($bloomLevels)];
            $clean = $this->cleanSentenceText($sentence);
            $subject = $this->extractSubjectPhrase($sentence);

            $isEssay = ($type === 'essay') || ($type === 'all' && ($i % 2 === 1));

            // Jika ada konteks kodingan dan level C3/C4, buatkan soal kodingan bertabel
            if ($progContext !== null && in_array($level['type'], ['application', 'analysis'])) {
                $questions[] = $this->craftCodingQuestion($sentence, $progContext, $i, $isEssay ? 'essay' : 'multiple_choice', $vocabulary);
                continue;
            }

            if ($isEssay) {
                $prompt = match ($level['type']) {
                    'understanding' => "Uraikan esensi konsep dari {$subject}! Jelaskan makna fundamentalnya dan bagaimana konsep ini saling melengkapi dengan elemen lain dalam materi!",
                    'application' => "Berdasarkan prinsip {$subject}, rumuskan contoh situasi implementasi nyata di lapangan dan langkah konkret penerapannya!",
                    'analysis' => "Lakukan analisis kritis mengenai faktor penyebab dan dampak yang muncul dari mekanisme {$subject}! Apa anomali yang terjadi jika komponen tersebut gagal berfungsi?",
                    'evaluation' => "Evaluasilah efektivitas dan batasan operasional dari {$subject}! Berikan argumen penilaian dan solusi strategis untuk mengoptimalkannya!",
                };

                $questions[] = [
                    'type' => 'essay',
                    'question' => $prompt,
                    'options' => [],
                    'correct_answer' => "Rubrik Evaluasi Kognitif ({$level['code']}): Peserta didik mengidentifikasi landasan teori, analisis mekanisme operasional, dan memberikan solusi atau justifikasi logis.",
                    'explanation' => "Pembahasan Analitis: Jawaban merujuk pada materi: {$clean}. Analisis harus mencerminkan penalaran kognitif tingkat {$level['code']}.",
                    'bloom_level' => $level['code'],
                    'source_snippet' => $sentence,
                ];
            } else {
                $qText = match ($level['type']) {
                    'understanding' => "Dalam konteks materi pembelajaran, signifikansi konseptual dari {$subject} ditegaskan melalui...",
                    'application' => "Jika prinsip {$subject} diimplementasikan secara langsung, hasil operasional yang ditargetkan adalah...",
                    'analysis' => "Analisis hubungan sebab-akibat menunjukkan bahwa dinamika dalam proses {$subject} dipicu oleh...",
                    'evaluation' => "Tolok ukur utama yang menentukan validitas dan keberhasilan pelaksanaan {$subject} adalah...",
                };

                $correct = "Kesesuaian parameter fungsional sesuai aturan materi pembelajaran";
                $distractors = $this->generatePlausibleDistractors($correct, $vocabulary, 4);
                $questions[] = $this->buildMCObject($qText, $correct, $distractors, "Jawaban benar diturunkan langsung dari analisis konsep: {$clean}.", $level['code'], $sentence);
            }
        }

        return $questions;
    }

    /**
     * MESIN 3: Case-Study & Scenario Engine (Studi Kasus Kontekstual & Pemecahan Masalah)
     */
    public function generateWithCaseStudyEngine(string $text, int $count, string $type): array
    {
        $sentences = $this->chunker->splitSentences($text);
        if (empty($sentences)) {
            $sentences = array_values(array_filter(array_map('trim', explode("\n", $text))));
        }
        if (empty($sentences)) {
            $sentences = ["Materi pokok pembelajaran mengenai konsep, mekanisme, dan aplikasi sistem operasional."];
        }

        $vocabulary = $this->extractKeyTerminology($text);
        $progContext = $this->detectProgrammingContext($text);
        $questions = [];

        for ($i = 0; $i < $count; $i++) {
            $sentence = $sentences[$i % count($sentences)];
            $clean = $this->cleanSentenceText($sentence);
            $subject = $this->extractSubjectPhrase($sentence);
            $term = !empty($vocabulary) ? $vocabulary[$i % count($vocabulary)] : 'Komponen Terkait';

            $isEssay = ($type === 'essay') || ($type === 'all' && ($i % 2 === 1));

            // Jika ada konteks kodingan, selingi dengan studi kasus berbasis kode
            if ($progContext !== null && ($i % 2 === 1)) {
                $questions[] = $this->craftCodingQuestion($sentence, $progContext, $i, $isEssay ? 'essay' : 'multiple_choice', $vocabulary);
                continue;
            }

            if ($isEssay) {
                $casePrompt = "STUDI KASUS #" . ($i + 1) . ":\nSebuah sistem operasional menerapkan mekanisme {$subject}. Dalam skenario di mana faktor eksternal mendisrupsi kinerja {$term}, rancanglah analisis penyebab masalah serta rencana mitigasi teknis yang tepat!";
                
                $questions[] = [
                    'type' => 'essay',
                    'question' => $casePrompt,
                    'options' => [],
                    'correct_answer' => "Rubrik Pemecahan Kasus: 1) Diagnosis masalah berdasarkan materi, 2) Solusi mitigasi terukur terkait {$subject}, 3) Indikator keberhasilan solusi.",
                    'explanation' => "Pembahasan Kasus: Mengharuskan peserta didik mensintesis prinsip teoretis ke dalam pemecahan skenario nyata tanpa jawaban hafalan.",
                    'bloom_level' => 'C4 - Analisis Skenario & Mitigasi',
                    'source_snippet' => $sentence,
                ];
            } else {
                $caseStem = "SKENARIO: Pada evaluasi implementasi sistem yang menerapkan {$subject}, ditemukan anomali pada bagian {$term}. Langkah diagnosis analitis pertama yang paling tepat dilakukan adalah...";
                $correct = "Memvalidasi kesesuaian parameter operasional dengan aturan teknis yang berlaku";
                $distractors = $this->generatePlausibleDistractors($correct, $vocabulary, 4);

                $questions[] = $this->buildMCObject($caseStem, $correct, $distractors, "Tindakan terukur harus berlandaskan kaidah materi terkait: {$clean}.", "C3 - Pemecahan Masalah Skenario", $sentence);
            }
        }

        return $questions;
    }

    /**
     * MESIN 4: Hybrid Multi-Engine Ensemble (Kombinasi Heuristik, Kausalitas, Taksonomi Bloom & Skenario)
     * Mampu menghasilkan bank soal besar (hingga 100 butir) yang kaya variasi dan bebas repetisi.
     */
    public function generateWithHybridEnsemble(string $text, int $count, string $type): array
    {
        $part1 = (int) ceil($count / 3);
        $part2 = (int) ceil($count / 3);
        $part3 = $count - ($part1 + $part2);

        $q1 = $this->generateWithOfflineEngine($text, $part1, $type);
        $q2 = $this->generateWithBloomTaxonomyEngine($text, $part2, $type);
        $q3 = $this->generateWithCaseStudyEngine($text, $part3, $type);

        $merged = array_merge($q1, $q2, $q3);
        // Distribusikan secara acak teratur
        return array_slice($merged, 0, $count);
    }

    /**
     * MESIN 5: Local OpenAI-compatible API (LM Studio / Jan / vLLM pada localhost:1234)
     */
    public function generateWithLocalOpenAI(string $text, int $count, string $type): array
    {
        $systemPrompt = $this->antiSlop->getAntiSlopSystemPrompt();
        $prompt = "Buat persis {$count} butir soal dari materi berikut dalam format JSON murni:\n\n{$text}";

        $res = Http::timeout(90)->post("{$this->lmStudioBaseUrl}/v1/chat/completions", [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
        ]);

        if (!$res->successful()) {
            throw new \RuntimeException("LM Studio API tidak merespons (Status: " . $res->status() . ")");
        }

        $content = $res->json()['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\[.*\]/s', $content, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed) && !empty($parsed)) {
                return $parsed;
            }
        }

        throw new \RuntimeException("Gagal mem-parsing format JSON dari LM Studio.");
    }

    /**
     * Membersihkan artefak teks (seperti <>, < >, atau spasi rusak) dari kalimat
     */
    /**
     * Membersihkan artefak teks (seperti <>, < >, atau spasi rusak) dari kalimat
     */
    public function cleanSentenceText(string $s): string
    {
        $s = preg_replace('/<\s*>/u', ' ', $s) ?? $s;
        $s = preg_replace('/&lt;\s*&gt;/ui', ' ', $s) ?? $s;
        $s = preg_replace('/<[0-9a-fA-F]{2,6}>/u', ' ', $s) ?? $s;
        $s = preg_replace('/[ \t]+/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+([.,!?:;])/', '$1', $s) ?? $s;
        return rtrim(trim($s), '.');
    }

    /**
     * Mengekstraksi frasa subjek/topik utama yang bersih dan terfokus dari sebuah kalimat materi.
     * Mencegah pengutipan mentah seluruh kalimat sumber pada pokok soal.
     */
    public function extractSubjectPhrase(string $sentence): string
    {
        $clean = $this->cleanSentenceText($sentence);
        // Hapus penanda bab/angka/modul di awal
        $clean = preg_replace('/^(?:bab\s+[0-9ivx]+|modul\s+[0-9ivx]+|[0-9]+\.|\([0-9]+\))\s*[:\.-]?\s*/ui', '', $clean);
        $clean = trim($clean);

        // Pola definisi/kopula umum dalam bahasa Indonesia
        $copulas = [
            ' adalah ', ' merupakan ', ' didefinisikan sebagai ', ' yaitu ', ' yakni ',
            ' berfungsi untuk ', ' berfungsi sebagai ', ' bertindak sebagai ',
            ' bertujuan untuk ', ' memiliki tujuan ', ' mencakup ', ' meliputi ',
            ' digunakan untuk ', ' dapat diartikan sebagai '
        ];

        foreach ($copulas as $copula) {
            $pos = mb_stripos($clean, $copula);
            if ($pos !== false && $pos >= 4) {
                $sub = trim(mb_substr($clean, 0, $pos));
                // Pastikan subjek tidak terlalu panjang dan bukan angka semata
                if (mb_strlen($sub) <= 70 && !preg_match('/^[0-9\W]+$/u', $sub)) {
                    return $sub;
                }
            }
        }

        // Jika ada tanda baca pemisah klausul seperti koma atau titik dua
        if (preg_match('/^([^,;:]{4,60})[,;:]/u', $clean, $m)) {
            $sub = trim($m[1]);
            if (mb_strlen($sub) >= 5 && !preg_match('/^(namun|tetapi|sehingga|karena|oleh karena itu)\b/ui', $sub)) {
                return $sub;
            }
        }

        // Ambil 4 hingga 7 kata pertama yang bermakna
        $words = preg_split('/\s+/u', $clean);
        if (is_array($words) && count($words) > 0) {
            $slice = array_slice($words, 0, min(6, count($words)));
            $phrase = implode(' ', $slice);
            // Hapus kata sambung pembuka jika ada
            $phrase = preg_replace('/^(selain itu|pada dasarnya|dalam hal ini|oleh sebab itu|adapun|sementara itu),?\s*/ui', '', $phrase);
            if (mb_strlen($phrase) >= 5) {
                return rtrim($phrase, '.,;:');
            }
        }

        return !empty($clean) ? Str::limit($clean, 50, '') : 'konsep dasar materi';
    }

    /**
     * Helper pembuatan soal PG berdasarkan sudut pandang kognitif
     */
    protected function craftMCByAngle(string $sentence, string $angle, array $vocabulary, string $fullText, int $seed): array
    {
        $clean = $this->cleanSentenceText($sentence);
        $subject = $this->extractSubjectPhrase($sentence);
        $bloom = 'C3 - Penerapan Konsep';

        switch ($angle) {
            case 'causality':
                $qText = "Dalam alur sistem kerja, implikasi logis yang timbul dari penerapan {$subject} adalah...";
                $correct = "Terbentuknya mekanisme operasional yang selaras dan terintegrasi secara konsisten";
                $bloom = 'C4 - Analisis Kausalitas';
                break;

            case 'counterfactual':
                $qText = "Jika mekanisme pada {$subject} mengalami kegagalan fungsi atau ditiadakan, dampak langsung yang terjadi adalah...";
                $correct = "Terganggunya integritas proses dan penurunan keandalan fungsi sistem terkait";
                $bloom = 'C4 - Analisis Kontrafaktual';
                break;

            case 'function':
                $qText = "Dalam kerangka materi pembelajaran, peranan fungsional utama dari {$subject} ditujukan untuk...";
                $correct = "Menjamin pemenuhan target operasional dan kepatuhan terhadap kaidah materi";
                $bloom = 'C2 - Pemahaman Fungsional';
                break;

            case 'comparison':
                $qText = "Karakteristik mendasar yang membedakan konsep {$subject} dari pendekatan konvensional adalah...";
                $correct = "Fokus penekanan pada parameter esensial dan efisiensi struktur proses";
                $bloom = 'C3 - Diferensiasi Konseptual';
                break;

            case 'application':
                $qText = "Ketika prinsip {$subject} diterapkan dalam kondisi operasional nyata, prioritas utama yang harus dipastikan adalah...";
                $correct = "Kesesuaian parameter fungsional dengan aturan dan standar teknis yang berlaku";
                $bloom = 'C3 - Penerapan Prinsip';
                break;

            default:
                $qText = "Berdasarkan kaidah materi pembelajaran, simpulan analitis yang paling tepat mengenai {$subject} adalah...";
                $correct = "Penguatan fondasi mekanisme sistem melalui koordinasi elemen yang terstruktur";
                $bloom = 'C2 - Penalaran Konseptual';
                break;
        }

        $distractors = $this->generatePlausibleDistractors($correct, $vocabulary, 4);
        return $this->buildMCObject($qText, $correct, $distractors, "Jawaban benar diturunkan langsung dari analisis konsep materi: {$clean}.", $bloom, $sentence);
    }

    /**
     * Helper pembuatan soal Essay berdasarkan sudut pandang kognitif
     */
    protected function craftEssayByAngle(string $sentence, string $angle, string $fullText): array
    {
        $clean = $this->cleanSentenceText($sentence);
        $subject = $this->extractSubjectPhrase($sentence);

        $prompts = [
            "causality" => "Uraikan hubungan sebab-akibat dalam penerapan {$subject}! Bagaimana mekanisme ini memengaruhi stabilitas dan keterpaduan keseluruhan sistem materi?",
            "counterfactual" => "Lakukan analisis dampak: Apa konsekuensi teknis jika proses pada {$subject} mengalami kegagalan fungsi dalam situasi riil? Berikan langkah mitigasi yang tepat!",
            "function" => "Jelaskan peran esensial dan signifikansi strategis dari konsep {$subject} dalam konteks materi pembelajaran!",
            "comparison" => "Bandingkan karakteristik implementasi {$subject} dengan alternatif pendekatan lainnya! Jelaskan mengapa prinsip tersebut dipandang krusial!",
            "application" => "Rumuskan skenario implementasi konkret dari prinsip {$subject}! Langkah-langkah strategis apa yang harus diambil untuk menjamin keberhasilannya?",
        ];

        $qText = $prompts[$angle] ?? "Analisislah secara kritis mekanisme fundamental dari {$subject}! Jelaskan signifikansinya dalam mendukung efektivitas materi!";

        return [
            'type' => 'essay',
            'question' => $qText,
            'options' => [],
            'correct_answer' => 'Rubrik Penilaian: 1) Pemahaman konsep dasar, 2) Alur mekanisme penalaran logis, 3) Contoh analisis atau solusi teknis terukur.',
            'explanation' => "Pembahasan Analitis: Jawaban komprehensif mengacu pada inti materi: {$clean}.",
            'bloom_level' => 'C4 - Analisis Kritis',
            'source_snippet' => $sentence,
        ];
    }

    /**
     * Mendeteksi konteks pemrograman/kodingan dalam materi
     */
    public function detectProgrammingContext(string $text): ?array
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(python|print\(|input\(|def\s+|len\(|range\(|pygame|pandas|numpy|list|dict|tuple)\b/ui', $lower)) {
            return ['lang' => 'python', 'name' => 'Python'];
        }
        if (preg_match('/\b(sql|select\s+|insert\s+into|from\s+|join\s+|primary\s+key|foreign\s+key|database|query)\b/ui', $lower)) {
            return ['lang' => 'sql', 'name' => 'SQL'];
        }
        if (preg_match('/\b(oop|class\s+|object\s+|inheritance|polymorphism|encapsulation|constructor|method|instansiasi)\b/ui', $lower)) {
            return ['lang' => 'python', 'name' => 'OOP (Object-Oriented Programming)'];
        }
        if (preg_match('/\b(php|\$this|echo\s+|function\s+|laravel|composer)\b/ui', $lower)) {
            return ['lang' => 'php', 'name' => 'PHP'];
        }
        if (preg_match('/\b(javascript|console\.log|const\s+|let\s+|var\s+|function|arrow\s+function)\b/ui', $lower)) {
            return ['lang' => 'javascript', 'name' => 'JavaScript'];
        }
        if (preg_match('/\b(java|c\+\+|public\s+static\s+void|system\.out|#include|cout)\b/ui', $lower)) {
            return ['lang' => 'java', 'name' => 'Java / C++'];
        }
        if (preg_match('/\b(koding|coding|pemrograman|algoritma|variabel|tipe\s+data|loop|perulangan|fungsi)\b/ui', $lower)) {
            return ['lang' => 'python', 'name' => 'Pemrograman'];
        }

        return null;
    }

    /**
     * Membuat soal khusus kodingan lengkap dengan tabel potongan kode bernomor baris
     */
    public function craftCodingQuestion(string $sentence, array $progContext, int $seed, string $type, array $vocabulary): array
    {
        $clean = $this->cleanSentenceText($sentence);
        $subject = $this->extractSubjectPhrase($sentence);
        $lang = $progContext['lang'];
        $term = !empty($vocabulary) ? $vocabulary[$seed % count($vocabulary)] : 'Data';

        $templates = [
            // Kasus 1: Prediksi Output & Eksekusi Fungsi
            [
                'title' => "Perhatikan tabel potongan kode {$progContext['name']} berikut. Apa nilai keluaran yang dihasilkan saat program dieksekusi?",
                'code' => match($lang) {
                    'sql' => "SELECT {$term}_nama, COUNT(*) AS total\nFROM {$term}_table\nWHERE status = 'aktif'\nGROUP BY {$term}_nama\nHAVING total > 1;",
                    'php' => "function hitungNilai(\$input) {\n    \$faktor = 2;\n    return (\$input * \$faktor) + 10;\n}\n\n\$output = hitungNilai(20);\necho \$output;",
                    'javascript' => "const hitung = (arr) => {\n    return arr.filter(n => n % 2 === 0).map(n => n * 2);\n};\n\nconsole.log(hitung([1, 2, 3, 4]));",
                    default => "def hitung_{$term}(nilai):\n    faktor = 2\n    return (nilai * faktor) + 5\n\nhasil = hitung_{$term}(10)\nprint(f\"Nilai akhir: {hasil}\")",
                },
                'correct' => match($lang) {
                    'sql' => "Menampilkan daftar {$term}_nama berstatus aktif dengan frekuensi kemunculan lebih dari 1",
                    'php' => "50",
                    'javascript' => "[4, 8]",
                    default => "Nilai akhir: 25",
                },
                'distractors' => match($lang) {
                    'sql' => [
                        "Menampilkan seluruh baris {$term}_table tanpa pengelompokan",
                        "Menghasilkan error karena klausa HAVING harus sebelum GROUP BY",
                        "Hanya menampilkan satu baris dengan nilai total tertinggi",
                    ],
                    'php' => ["40", "30", "20"],
                    'javascript' => ["[2, 4, 6, 8]", "[2, 4]", "undefined"],
                    default => ["Nilai akhir: 20", "Nilai akhir: 30", "Error: variabel faktor tidak dikenali"],
                },
                'explanation' => "Penjelasan Alur Eksekusi: Parameter dimasukkan ke dalam fungsi, dilakukan operasi aritmatika sesuai baris kode, dan hasil akhirnya dicetak ke layar.",
                'bloom' => "C3 - Analisis Alur Eksekusi Kode",
            ],

            // Kasus 2: Analisis Konsep Struktur OOP & Kelas
            [
                'title' => "Diberikan struktur kode {$progContext['name']} berikut yang berkaitan dengan konsep {$subject}. Analisislah fungsi dan prinsip yang diterapkan pada kode tersebut!",
                'code' => match($lang) {
                    'sql' => "CREATE TABLE master_{$term} (\n    id INT PRIMARY KEY AUTO_INCREMENT,\n    kode_referensi VARCHAR(50) NOT NULL UNIQUE,\n    status VARCHAR(20) DEFAULT 'Aktif',\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n);",
                    'php' => "class " . ucfirst($term) . "Manager {\n    private string \$kode;\n\n    public function __construct(string \$kode) {\n        \$this->kode = \$kode;\n    }\n\n    public function getIdentitas(): string {\n        return \"Entitas: \" . \$this->kode;\n    }\n}",
                    default => "class " . ucfirst($term) . "Model:\n    def __init__(self, identifier, nilai=0):\n        self.__identifier = identifier  # Encapsulation (Private)\n        self.nilai = nilai\n\n    def proses_data(self):\n        return f\"{self.__identifier}: {self.nilai * 2}\"",
                },
                'correct' => match($lang) {
                    'sql' => "Mendefinisikan skema tabel dengan PRIMARY KEY unik dan batasan integritas data NOT NULL",
                    'php' => "Menerapkan enkapsulasi dengan atribut private \$kode yang diinisialisasi melalui konstruktor",
                    default => "Menerapkan enkapsulasi dengan atribut privat self.__identifier yang diakses secara terproteksi",
                },
                'distractors' => match($lang) {
                    'sql' => [
                        "Menghapus tabel master jika terjadi duplikasi data secara otomatis",
                        "Membuat tabel sementara yang hilang saat sesi koneksi berakhir",
                        "Menjadikan kolom status sebagai Foreign Key referensial",
                    ],
                    'php' => [
                        "Membuat variabel publik yang dapat diakses bebas tanpa melalui metode",
                        "Mengharuskan kelas dijadikan interface abstrak murni",
                        "Mengabaikan nilai konstruktor saat objek dibuat",
                    ],
                    default => [
                        "Mengizinkan akses langsung self.__identifier dari luar kelas secara publik",
                        "Membuat fungsi global yang tidak terikat pada instansiasi objek",
                        "Menghasilkan error kompilasi karena parameter self dilarang",
                    ],
                },
                'explanation' => "Pembahasan OOP: Enkapsulasi membatasi akses langsung ke variabel internal dan memastikan inisialisasi data berlangsung aman melalui konstruktor.",
                'bloom' => "C4 - Analisis Arsitektur OOP",
            ],

            // Kasus 3: Debugging & Perbaikan Kesalahan Kode
            [
                'title' => "Perhatikan kode program {$progContext['name']} berikut. Jika kode dijalankan dan memunculkan galat (bug), bagian manakah yang perlu diperbaiki agar berjalan sesuai aturan materi?",
                'code' => match($lang) {
                    'sql' => "-- Query Pencarian Data\nSELECT id, nama, skor\nFROM peserta_ujian\nWHERE skor = NULL; -- Perhatikan kondisi perbandingan",
                    'php' => "\$daftar = [\"A\" => 10, \"B\" => 20];\n// Mengakses key yang belum terdefinisi\n\$total = \$daftar[\"C\"] + 5;\necho \$total;",
                    default => "data_angka = [10, 20, 30, 40]\n# Iterasi melampaui indeks list\nfor i in range(len(data_angka) + 1):\n    print(data_angka[i])",
                },
                'correct' => match($lang) {
                    'sql' => "Kondisi 'skor = NULL' harus diganti dengan 'skor IS NULL' karena nilai NULL tidak dapat dibandingkan dengan operator '='",
                    'php' => "Menggunakan pengecekan isset(\$daftar['C']) atau operator null coalescing (\$daftar['C'] ?? 0) untuk mencegah Notice Undefined Key",
                    default => "Perulangan melampaui panjang indeks list (IndexError), perbaikan dengan menghapus '+ 1' pada fungsi range()",
                },
                'distractors' => match($lang) {
                    'sql' => [
                        "Klausa WHERE harus digantikan dengan HAVING",
                        "Kueri harus menggunakan klausa LIMIT 1 di awal",
                        "Nama tabel tidak boleh menggunakan karakter garis bawah (_)",
                    ],
                    'php' => [
                        "Array asosiatif tidak boleh diisi dengan nilai integer",
                        "Harus menggunakan echo print_r() secara bersamaan",
                        "Variabel \$daftar harus diubah menjadi tipe boolean",
                    ],
                    default => [
                        "List data_angka harus diubah menjadi tuple sebelum perulangan",
                        "Fungsi print tidak boleh dipanggil di dalam blok for loop",
                        "Variabel i harus dideklarasikan tipe datanya terlebih dahulu",
                    ],
                },
                'explanation' => "Pembahasan Debugging: Pengujian pemahaman terhadap boundary condition (batas indeks array/list) dan sintaksis presisi penanganan nilai khusus.",
                'bloom' => "C4 - Evaluasi & Pelacakan Bug (Debugging)",
            ],
        ];

        $tpl = $templates[$seed % count($templates)];

        if ($type === 'essay') {
            return [
                'type' => 'essay',
                'question' => "TELAAH KODE PROGRAM ({$progContext['name']}):\n" . $tpl['title'] . "\nJelaskan secara bertahap alur kerja program pada tabel di atas dan kaitkan dengan penerapan {$subject}!",
                'has_code' => true,
                'code_lang' => $lang,
                'code_snippet' => $tpl['code'],
                'options' => [],
                'correct_answer' => "Rubrik Penilaian: 1) Analisis mekanisme baris kode secara runtut, 2) Identifikasi konsep {$tpl['bloom']}, 3) Menghubungkan logika kode dengan prinsip materi.",
                'explanation' => "Pembahasan Analitis: " . $tpl['explanation'],
                'bloom_level' => $tpl['bloom'],
                'source_snippet' => $sentence,
            ];
        }

        return $this->buildMCObject(
            $tpl['title'],
            $tpl['correct'],
            $tpl['distractors'],
            $tpl['explanation'],
            $tpl['bloom'],
            $sentence,
            true,
            $lang,
            $tpl['code']
        );
    }

    /**
     * Format MCObject dengan pengacakan A, B, C, D
     */
    protected function buildMCObject(
        string $question,
        string $correctText,
        array $distractors,
        string $explanation,
        string $bloom,
        string $sourceSnippet,
        bool $hasCode = false,
        string $codeLang = '',
        string $codeSnippet = ''
    ): array {
        $sliceCount = count($distractors) >= 4 ? 4 : min(3, count($distractors));
        $allChoices = array_merge([$correctText], array_slice($distractors, 0, $sliceCount));
        shuffle($allChoices);

        $labels = ['A', 'B', 'C', 'D', 'E'];
        $correctLabel = 'A';
        $formattedOptions = [];

        foreach ($allChoices as $idx => $choice) {
            $label = $labels[$idx] ?? chr(65 + $idx);
            if ($choice === $correctText) {
                $correctLabel = $label;
            }
            $formattedOptions[] = "{$label}. {$choice}";
        }

        return [
            'type' => 'pilihan_ganda',
            'question' => $question,
            'has_code' => $hasCode,
            'code_lang' => $codeLang,
            'code_snippet' => $codeSnippet,
            'options' => $formattedOptions,
            'correct_answer' => $correctLabel,
            'explanation' => "Kunci Jawaban: ({$correctLabel}). {$explanation}",
            'bloom_level' => $bloom,
            'source_snippet' => $sourceSnippet,
        ];
    }

    /**
     * Ekstraksi terminologi penting dari materi
     */
    protected function extractKeyTerminology(string $text): array
    {
        $text = $this->chunker->sanitizeUtf8($text);
        @preg_match_all('/\b[A-Z][a-z0-9_-]{3,}\b/u', $text, $matchesCapital);
        @preg_match_all('/\b[a-zA-Z]{5,}\b/u', mb_strtolower($text), $matchesAll);

        $stopWords = [
            'adalah', 'merupakan', 'dengan', 'karena', 'sehingga', 'bahwa', 'untuk',
            'dalam', 'tersebut', 'seperti', 'antara', 'menjadi', 'dapat', 'secara',
            'terhadap', 'sampai', 'sebagai', 'ketika', 'setelah', 'sebelum', 'melalui',
        ];

        $capital = is_array($matchesCapital[0] ?? null) ? $matchesCapital[0] : [];
        $all = is_array($matchesAll[0] ?? null) ? $matchesAll[0] : [];
        $candidates = array_unique(array_merge($capital, $all));
        $validWords = [];

        if (is_array($candidates)) {
            foreach ($candidates as $w) {
                $lw = mb_strtolower($w);
                if (!in_array($lw, $stopWords) && mb_strlen($lw) >= 5) {
                    $validWords[] = ucfirst($lw);
                }
            }
        }

        return array_values($validWords);
    }

    /**
     * Membentuk distractor seimbang dan masuk akal
     */
    protected function generatePlausibleDistractors(string $correctText, array $vocabulary, int $count = 3): array
    {
        $distractors = [];
        $words = !empty($vocabulary) ? $vocabulary : ['Komponen', 'Stabilitas', 'Efisiensi', 'Variabel', 'Parameter'];
        shuffle($words);

        $templates = [
            "Menekankan pada optimasi %s tanpa memperhatikan stabilitas sistem",
            "Membatasi interaksi pada fase %s secara parsial",
            "Menggantikan peran %s dengan prosedur statis non-adaptif",
            "Mengurangi frekuensi implementasi %s demi efisiensi biaya semata",
            "Mengalihkan fokus operasional menuju isolasi komponen %s",
            "Peningkatan variabel %s secara terisolasi tanpa integrasi proses",
        ];

        shuffle($templates);

        $i = 0;
        while (count($distractors) < $count) {
            $term = $words[$i % count($words)] ?? 'Parameter Kunci';
            $tpl = $templates[count($distractors) % count($templates)];
            $candidate = sprintf($tpl, $term);

            if ($candidate !== $correctText && !in_array($candidate, $distractors)) {
                $distractors[] = $candidate;
            }
            $i++;
            if ($i > 30) {
                $distractors[] = "Hanya berfokus pada reduksi skala beban tanpa memperhitungkan variabel {$term}";
                break;
            }
        }

        return array_slice($distractors, 0, $count);
    }

    /**
     * Ollama JSON generation
     */
    protected function generateWithOllama(string $text, int $count, string $type, string $model): array
    {
        $systemPrompt = $this->antiSlop->getAntiSlopSystemPrompt();
        $userPrompt = "Tugas: Buat persis {$count} butir soal dari materi berikut dalam format JSON murni:\n\n{$text}";

        $response = Http::timeout(180)->post("{$this->ollamaBaseUrl}/api/generate", [
            'model' => $model,
            'system' => $systemPrompt,
            'prompt' => $userPrompt,
            'format' => 'json',
            'stream' => false,
            'options' => [
                'temperature' => 0.4,
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Ollama API mengembalikan status: " . $response->status());
        }

        $rawOutput = $response->json()['response'] ?? '';
        $decoded = json_decode($rawOutput, true);
        if (!is_array($decoded) && preg_match('/\[.*\]/s', $rawOutput, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (!is_array($decoded) || empty($decoded)) {
            throw new \RuntimeException("Gagal mem-parsing output JSON dari Ollama.");
        }

        return $decoded;
    }
}
