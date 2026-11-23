<?php

namespace App\Http\Controllers;

use App\Services\DocumentTextExtractorService;
use App\Services\LocalSoalGeneratorService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SoalGeneratorController extends Controller
{
    protected LocalSoalGeneratorService $generatorService;
    protected DocumentTextExtractorService $extractorService;

    public function __construct(
        LocalSoalGeneratorService $generatorService,
        DocumentTextExtractorService $extractorService
    ) {
        $this->generatorService = $generatorService;
        $this->extractorService = $extractorService;
    }

    /**
     * Tampilan dashboard generator soal
     */
    public function index()
    {
        $localStatus = $this->generatorService->getAvailableLocalModels();
        return view('generator', [
            'localStatus' => $localStatus,
            'results' => session('results'),
            'inputs' => session('inputs', [
                'percentage' => 50,
                'count' => 5,
                'type' => 'all',
                'material' => '',
                'coding_mode' => 'none',
            ]),
            'materialsSummary' => session('materialsSummary'),
        ]);
    }

    /**
     * Memproses pembuatan soal dari SATU ATAU BANYAK materi tanpa batasan
     */
    public function generate(Request $request)
    {
        // Hilangkan batasan waktu eksekusi & memori agar dapat memproses materi besar tanpa batas
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '-1');

        $request->validate([
            'material' => 'nullable|string',
            'materials' => 'nullable|array',
            'materials.*' => 'nullable|string',
            'material_file' => 'nullable|file',
            'material_files' => 'nullable|array',
            'material_files.*' => 'nullable|file',
            'percentage' => 'required|integer|min:1|max:100',
            'count' => 'required|integer|min:1|max:100',
            'type' => 'required|in:all,multiple_choice,essay',
            'model' => 'nullable|string',
            'coding_mode' => 'nullable|in:auto,none,code_only',
        ]);

        $combinedCorpusParts = [];
        $sourcesMetadata = [];

        // 1. Kumpulkan semua berkas yang diunggah (Single maupun Multiple Files tanpa batas)
        $allFiles = [];
        if ($request->hasFile('material_files')) {
            $allFiles = array_merge($allFiles, (array) $request->file('material_files'));
        }
        if ($request->hasFile('material_file')) {
            $allFiles[] = $request->file('material_file');
        }

        if (!empty($allFiles)) {
            $multiExtraction = $this->extractorService->extractMultiple($allFiles);
            if (!empty($multiExtraction['combined_text'])) {
                $combinedCorpusParts[] = $multiExtraction['combined_text'];
            }
            foreach ($multiExtraction['files_metadata'] as $meta) {
                $sourcesMetadata[] = [
                    'title' => $meta['name'],
                    'type' => 'File: ' . $meta['format'],
                    'words' => $meta['words'],
                    'details' => $meta['details'] . " ({$meta['size_kb']} KB)",
                    'status' => $meta['status'],
                ];
            }
        }

        // 2. Kumpulkan semua input teks materi (Bisa 1 atau banyak blok teks/bab)
        $textInputs = [];
        if ($request->filled('material')) {
            $textInputs[] = trim($request->input('material'));
        }
        if ($request->has('materials') && is_array($request->input('materials'))) {
            foreach ($request->input('materials') as $textBlock) {
                $trimmed = trim($textBlock ?? '');
                if (!empty($trimmed)) {
                    $textInputs[] = $trimmed;
                }
            }
        }

        foreach ($textInputs as $idx => $txt) {
            $wordCount = str_word_count($txt);
            $partNum = $idx + 1;
            $combinedCorpusParts[] = "=== SESI MATERI TEKS #{$partNum} ===\n" . $txt;
            $sourcesMetadata[] = [
                'title' => "Materi Teks #{$partNum}",
                'type' => 'Teks / Catatan Manual',
                'words' => $wordCount,
                'details' => "Input teks manual ({$wordCount} kata)",
                'status' => 'success',
            ];
        }

        $finalCorpus = trim(implode("\n\n" . str_repeat('=', 60) . "\n\n", $combinedCorpusParts));

        if (empty($finalCorpus)) {
            return back()->withErrors([
                'material' => 'Materi tidak boleh kosong. Silakan unggah satu atau beberapa berkas, atau masukkan teks materi pembelajaran.'
            ])->withInput();
        }

        try {
            $percentage = (int) $request->input('percentage', 50);
            $count = (int) $request->input('count', 5);
            $type = $request->input('type', 'all');
            $model = $request->input('model', 'offline_engine');
            $codingMode = $request->input('coding_mode', 'none');

            $results = $this->generatorService->generate($finalCorpus, $percentage, $count, $type, $model, $codingMode);

            $totalSources = count($sourcesMetadata);
            $statusMsg = "Sukses memproses {$totalSources} sumber materi dan membuat " . count($results['questions']) . " butir soal dengan Anti-Slop filter.";

            $localStatus = $this->generatorService->getAvailableLocalModels();

            return view('generator', [
                'localStatus' => $localStatus,
                'results' => $results,
                'inputs' => [
                    'material' => $request->input('material', ''),
                    'materials' => $request->input('materials', []),
                    'percentage' => $percentage,
                    'count' => $count,
                    'type' => $type,
                    'model' => $model,
                    'coding_mode' => $codingMode,
                ],
                'materialsSummary' => [
                    'total_sources' => $totalSources,
                    'total_words_corpus' => str_word_count($finalCorpus),
                    'sources' => $sourcesMetadata,
                ],
                'status' => $statusMsg,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error generating soal: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->withErrors(['error' => 'Gagal membuat soal: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Ekspor hasil soal ke format File (.xlsx, .csv, atau .txt)
     */
    public function export(Request $request)
    {
        $questionsJson = $request->input('questions_data');
        $format = strtolower($request->input('format', 'xlsx'));

        $questions = json_decode($questionsJson, true) ?? [];

        // 1. Ekspor Format XLSX (Microsoft Excel Sesuai Template Bank Soal Sekolah)
        if ($format === 'xlsx') {
            $filename = 'bank-soal-' . date('Ymd-His') . '.xlsx';
            $xlsxService = app(\App\Services\XlsxExportService::class);
            $filePath = $xlsxService->generateBankSoalXlsx($questions);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        // 2. Ekspor Format CSV sesuai Template Bank Soal Sekolah
        if ($format === 'csv') {
            $filename = 'bank-soal-' . date('Ymd-His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            return response()->stream(function () use ($questions) {
                $out = fopen('php://output', 'w');

                // Tulis UTF-8 BOM agar terbaca sempurna di Microsoft Excel & Web Portal Sekolah
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

                // Header template persis sesuai format bank soal sekolah
                fputcsv($out, [
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

                    // Batang soal (sertakan potongan kodingan jika ada)
                    $stem = $q['question'] ?? '';
                    if (!empty($q['code_snippet'])) {
                        $lang = strtoupper($q['code_lang'] ?? 'KODE');
                        $stem .= "\n\n[KODE PROGRAM {$lang}]:\n" . $q['code_snippet'];
                    }

                    // Pilihan jawaban A, B, C, D, E (hilangkan prefiks 'A. ', 'B. ' dsb.)
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

                    // Kunci jawaban (huruf tunggal A/B/C/D/E untuk PG, kosong untuk Essai)
                    $kunci = '';
                    if ($isPG) {
                        $kunciRaw = trim($q['correct_answer'] ?? '');
                        if (preg_match('/^([A-E])\b/i', $kunciRaw, $km)) {
                            $kunci = strtoupper($km[1]);
                        } else {
                            $kunci = $kunciRaw;
                        }
                    }

                    // Bobot Essai (kosong untuk PG, nilai 20 untuk Essai sesuai template)
                    $bobot = $isPG ? '' : 20;

                    fputcsv($out, [
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

                fclose($out);
            }, 200, $headers);
        }

        // Ekspor Format Teks (.txt)
        $filename = 'bank-soal-' . date('Ymd-His') . '.' . ($format === 'markdown' ? 'md' : 'txt');
        $headers = [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($questions) {
            $out = fopen('php://output', 'w');
            fputs($out, "========================================================\n");
            fputs($out, "PAKET SOAL EVALUASI PEMBELAJARAN (BANK SOAL)\n");
            fputs($out, "Dibuat Secara Lokal - Tanggal: " . date('d F Y H:i:s') . "\n");
            fputs($out, "========================================================\n\n");

            foreach ($questions as $q) {
                $num = $q['number'] ?? 1;
                $type = ($q['type'] ?? '') === 'pilihan_ganda' ? 'PILIHAN GANDA' : 'ESSAY / ANALISIS';
                $bloom = $q['bloom_level'] ?? 'Standar';

                fputs($out, "[$num] ($type | $bloom)\n");
                fputs($out, $q['question'] . "\n");

                if (!empty($q['code_snippet'])) {
                    $lang = strtoupper($q['code_lang'] ?? 'KODE');
                    fputs($out, "\n--- TABEL KODE PROGRAM ({$lang}) ---\n");
                    $lines = explode("\n", rtrim($q['code_snippet']));
                    foreach ($lines as $i => $l) {
                        fputs($out, sprintf(" %3d | %s\n", $i + 1, $l));
                    }
                    fputs($out, "-------------------------------------\n");
                }

                if (!empty($q['options'])) {
                    fputs($out, "\n");
                    foreach ($q['options'] as $opt) {
                        fputs($out, "    $opt\n");
                    }
                }

                fputs($out, "\nKUNCI / RUBRIK: " . ($q['correct_answer'] ?? '-') . "\n");
                fputs($out, "PEMBAHASAN: " . ($q['explanation'] ?? '-') . "\n");
                if (!empty($q['source_snippet'])) {
                    fputs($out, "RUJUKAN MATERI: \"" . $q['source_snippet'] . "\"\n");
                }
                fputs($out, "--------------------------------------------------------\n\n");
            }

            fclose($out);
        }, 200, $headers);
    }
}
