<?php

namespace App\Console\Commands;

use App\Services\DocumentTextExtractorService;
use App\Services\LocalSoalGeneratorService;
use Illuminate\Console\Command;

class GenerateQuestions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'soal:generate
                            {--input=* : Path ke satu atau BANYAK file materi (contoh: --input=bab1.pdf --input=bab2.docx atau dipisah koma)}
                            {--percent=50 : Berapa persen porsi materi yang masuk (1-100)}
                            {--count=5 : Jumlah butir soal yang dibuat (1-100)}
                            {--type=all : Tipe soal (all, multiple_choice, essay)}
                            {--model=offline_engine : Pilihan mesin (offline_engine, bloom_engine, case_study_engine, hybrid_ensemble, lm_studio, atau nama model Ollama)}
                            {--output= : Path output file hasil (.txt)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate paket soal pembelajaran secara lokal dari SATU atau BANYAK materi tanpa batasan dengan porsi materi terpilih & skill Anti-Slop';

    /**
     * Execute the console command.
     */
    public function handle(
        LocalSoalGeneratorService $generatorService,
        DocumentTextExtractorService $extractorService
    ) {
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '-1');

        $inputRaw = $this->option('input');
        $percent   = (int) $this->option('percent');
        $count     = (int) $this->option('count');
        $type      = $this->option('type') ?: 'all';
        $model     = $this->option('model') ?: 'offline_engine';
        $outputPath = $this->option('output');

        $this->info("=================================================");
        $this->info("  SOAL GENERATOR LOKAL (MULTI-MATERI & ANTI-SLOP)");
        $this->info("=================================================");

        // Kumpulkan semua file input (bisa array opsi, koma, atau glob wildcard)
        $fileList = [];
        if (!empty($inputRaw)) {
            foreach ((array) $inputRaw as $item) {
                // Pecah jika dipisah koma
                $parts = explode(',', $item);
                foreach ($parts as $p) {
                    $trimmed = trim($p);
                    if (str_contains($trimmed, '*') || str_contains($trimmed, '?')) {
                        $matches = glob($trimmed);
                        if ($matches) {
                            $fileList = array_merge($fileList, $matches);
                        }
                    } elseif (file_exists($trimmed)) {
                        $fileList[] = $trimmed;
                    }
                }
            }
        }

        if (empty($fileList)) {
            $ask = $this->ask('Masukkan path file materi (bisa beberapa file dipisah koma, contoh: bab1.pdf, bab2.docx, materi.csv)');
            if ($ask) {
                foreach (explode(',', $ask) as $p) {
                    $trimmed = trim($p);
                    if (file_exists($trimmed)) {
                        $fileList[] = $trimmed;
                    }
                }
            }
        }

        if (empty($fileList)) {
            $this->error("Tidak ada file materi yang ditemukan.");
            return 1;
        }

        $this->line("• Memproses " . count($fileList) . " berkas materi tanpa batasan...");

        $multiResult = $extractorService->extractMultiple($fileList);
        $materialText = $multiResult['combined_text'];

        if (empty(trim($materialText))) {
            $this->error("Tidak ada teks materi yang berhasil diekstrak.");
            return 1;
        }

        $this->newLine();
        $this->info("✓ Rincian Sumber Materi yang Berhasil Digabungkan:");
        foreach ($multiResult['files_metadata'] as $idx => $meta) {
            $num = $idx + 1;
            $this->line("  [{$num}] {$meta['name']} ({$meta['format']}) - {$meta['words']} kata");
        }

        $this->line("• Total kata gabungan: " . $multiResult['total_words'] . " kata");
        $this->line("• Porsi cakupan materi: {$percent}%");
        $this->line("• Target jumlah soal: {$count} butir ({$type})");
        $this->line("• Mesin yang digunakan: {$model}");
        $this->line("• Menerapkan filter Anti-Slop (Zero cliches, HOTS, plausible distractors)...");

        try {
            $result = $generatorService->generate($materialText, $percent, $count, $type, $model);
        } catch (\Throwable $e) {
            $this->error("Gagal men-generate soal: " . $e->getMessage());
            return 1;
        }

        $questions = $result['questions'];
        $stats = $result['material_stats'];
        $antiSlop = $result['anti_slop_summary'];

        $this->newLine();
        $this->info("✓ Sukses! Berhasil membuat " . count($questions) . " butir soal dari " . count($fileList) . " materi.");
        $this->line("  - Mesin: " . $result['engine_used']);
        $this->line("  - Kata materi yang dicakup: " . $stats['words_sampled'] . " dari " . $stats['total_words_original'] . " kata (" . $stats['actual_percentage'] . "%)");
        $this->line("  - Rata-rata Skor Anti-Slop: " . $antiSlop['average_score'] . "/100 (" . $antiSlop['status'] . ")");
        $this->newLine();

        foreach ($questions as $q) {
            $this->line("--------------------------------------------------------------------------------");
            $this->info("#{$q['number']} [{$q['type']}] ({$q['bloom_level']}) | Anti-Slop: {$q['anti_slop_audit']['level']}");
            $this->line($q['question']);

            if (!empty($q['code_snippet'])) {
                $lang = strtoupper($q['code_lang'] ?? 'KODE');
                $this->comment("┌── [TABEL KODE PROGRAM: {$lang}] " . str_repeat('─', 35));
                $lines = explode("\n", rtrim($q['code_snippet']));
                foreach ($lines as $i => $l) {
                    $this->line(sprintf("│ %3d │ %s", $i + 1, $l));
                }
                $this->comment("└──" . str_repeat('─', 60));
            }

            if (!empty($q['options'])) {
                foreach ($q['options'] as $opt) {
                    $this->line("   {$opt}");
                }
            }
            $this->comment("Kunci: {$q['correct_answer']}");
            $this->line("Pembahasan: {$q['explanation']}");
        }
        $this->line("--------------------------------------------------------------------------------");

        if ($outputPath) {
            if (str_ends_with(strtolower($outputPath), '.xlsx')) {
                $xlsxSvc = app(\App\Services\XlsxExportService::class);
                $tmp = $xlsxSvc->generateBankSoalXlsx($questions);
                copy($tmp, $outputPath);
                @unlink($tmp);
                $this->info("✓ Berkas Bank Soal XLSX (Excel) berhasil diekspor ke: {$outputPath}");
            } elseif (str_ends_with(strtolower($outputPath), '.csv')) {
                $fp = fopen($outputPath, 'w');
                // UTF-8 BOM
                fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
                // Header sesuai template bank soal sekolah
                fputcsv($fp, [
                    'Soal/Pertanyaan',
                    'Jenis ( 1=PG, 2=Essai)',
                    'Jawaban A',
                    'Jawaban B',
                    'Jawaban C',
                    'Jawaban D',
                    'Jawaban E',
                    'Kunci Jawaban (A/B/C/D/E)',
                    'Bobot Essai',
                ]);

                foreach ($questions as $q) {
                    $isPG = ($q['type'] ?? '') === 'pilihan_ganda' || ($q['type'] ?? '') === 'multiple_choice';
                    $jenis = $isPG ? 1 : 2;

                    $stem = $q['question'] ?? '';
                    if (!empty($q['code_snippet'])) {
                        $lang = strtoupper($q['code_lang'] ?? 'KODE');
                        $stem .= "\n\n[KODE PROGRAM {$lang}]:\n" . $q['code_snippet'];
                    }

                    $optMap = ['A' => '', 'B' => '', 'C' => '', 'D' => '', 'E' => ''];
                    if ($isPG && !empty($q['options']) && is_array($q['options'])) {
                        foreach ($q['options'] as $idx => $opt) {
                            if (preg_match('/^([A-E])\.\s*(.*)$/us', $opt, $m)) {
                                $optMap[$m[1]] = trim($m[2]);
                            } else {
                                $labels = ['A', 'B', 'C', 'D', 'E'];
                                if (isset($labels[$idx])) {
                                    $optMap[$labels[$idx]] = trim($opt);
                                }
                            }
                        }
                    }

                    $kunci = '';
                    if ($isPG) {
                        $kunciRaw = trim($q['correct_answer'] ?? '');
                        if (preg_match('/^([A-E])\b/i', $kunciRaw, $km)) {
                            $kunci = strtoupper($km[1]);
                        } else {
                            $kunci = $kunciRaw;
                        }
                    }

                    $bobot = $isPG ? '' : 20;

                    fputcsv($fp, [
                        $stem,
                        $jenis,
                        $optMap['A'],
                        $optMap['B'],
                        $optMap['C'],
                        $optMap['D'],
                        $optMap['E'],
                        $kunci,
                        $bobot,
                    ]);
                }
                fclose($fp);
                $this->info("✓ Berkas Bank Soal CSV berhasil diekspor ke: {$outputPath}");
            } else {
                $content = "PAKET SOAL ANTI-SLOP (LARAVEL LOCAL GENERATOR - MULTI-MATERI)\n";
                $content .= "Jumlah Sumber Materi: " . count($fileList) . " berkas\n";
                $content .= "Daftar Sumber: " . implode(', ', array_map('basename', $fileList)) . "\n";
                $content .= "Tanggal: " . date('Y-m-d H:i:s') . "\n";
                $content .= "Cakupan Materi: {$percent}% ({$stats['words_sampled']} kata)\n\n";

                foreach ($questions as $q) {
                    $content .= "#{$q['number']} [{$q['type']} - {$q['bloom_level']}]\n";
                    $content .= $q['question'] . "\n";
                    if (!empty($q['options'])) {
                        foreach ($q['options'] as $opt) {
                            $content .= "   {$opt}\n";
                        }
                    }
                    $content .= "KUNCI: {$q['correct_answer']}\n";
                    $content .= "PEMBAHASAN: {$q['explanation']}\n";
                    $content .= "--------------------------------------------------------\n\n";
                }

                file_put_contents($outputPath, $content);
                $this->info("✓ Berkas naskah soal berhasil disimpan ke: {$outputPath}");
            }
        }

        return 0;
    }
}
