<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyusun Instrumen Asesmen & Bank Soal Ujian</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        
        /* Custom scrollbar untuk list berkas & tabel */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @media screen {
            .print-only { display: none !important; }
        }

        /* =========================================================================
           STYLING KHUSUS CETAK & SIMPAN PDF (12pt Times New Roman & Tabel Kodingan)
           ========================================================================= */
        @media print {
            @page {
                size: A4 portrait;
                margin: 20mm 15mm 20mm 15mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .no-print, header, footer, aside, #sidebarBackdrop, #sidebarPanel, button, form {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Times New Roman', Times, 'Nimbus Roman No9 L', serif !important;
                font-size: 12pt !important;
                line-height: 1.5 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            main {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .max-w-5xl, .max-w-7xl, .space-y-6, .space-y-4 {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Pengaturan Print Bank Soal Murni (Tanpa Kop Siswa) */
            .question-card {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                border: none !important;
                border-bottom: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 10pt 0 !important;
                margin-bottom: 8pt !important;
                background: transparent !important;
                font-family: 'Times New Roman', Times, serif !important;
            }

            /* Header Soal */
            .question-header {
                display: flex !important;
                align-items: baseline !important;
                margin-bottom: 4pt !important;
                padding-bottom: 0 !important;
                border-bottom: none !important;
                font-family: 'Times New Roman', Times, serif !important;
            }

            .question-number {
                font-family: 'Times New Roman', Times, serif !important;
                font-size: 12pt !important;
                font-weight: bold !important;
                color: #000000 !important;
                margin-right: 4pt !important;
            }

            .question-badge {
                font-family: 'Times New Roman', Times, serif !important;
                font-size: 10.5pt !important;
                font-weight: normal !important;
                color: #334155 !important;
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
            }

            /* Batang Soal (Teks Pertanyaan 12pt Times New Roman) */
            .question-stem {
                font-family: 'Times New Roman', Times, serif !important;
                font-size: 12pt !important;
                font-weight: normal !important;
                color: #000000 !important;
                line-height: 1.5 !important;
                text-align: justify !important;
                margin-bottom: 6pt !important;
            }

            /* Opsi Pilihan Ganda (12pt Times New Roman, Indentasi Rapi) */
            .options-container {
                display: block !important;
                margin-top: 4pt !important;
                padding-left: 18pt !important;
            }

            .option-item {
                font-family: 'Times New Roman', Times, serif !important;
                font-size: 12pt !important;
                color: #000000 !important;
                line-height: 1.45 !important;
                border: none !important;
                background: transparent !important;
                padding: 2pt 0 !important;
                margin-bottom: 3pt !important;
                text-align: left !important;
            }

            /* TABEL KODINGAN: Presisi seperti di generator */
            .code-table-container {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                background-color: #0f172a !important;
                border: 1px solid #1e293b !important;
                border-radius: 6px !important;
                margin: 8pt 0 10pt 0 !important;
                overflow: hidden !important;
                display: block !important;
                width: 100% !important;
            }

            .code-table-header {
                background-color: #020617 !important;
                border-bottom: 1px solid #1e293b !important;
                padding: 4pt 10pt !important;
                color: #94a3b8 !important;
                font-family: 'JetBrains Mono', monospace !important;
                font-size: 9pt !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
            }

            .code-table {
                width: 100% !important;
                border-collapse: collapse !important;
                background-color: #0f172a !important;
                font-family: 'JetBrains Mono', 'Courier New', monospace !important;
                font-size: 9.5pt !important;
                line-height: 1.4 !important;
            }

            .code-row {
                background-color: transparent !important;
            }

            .code-line-number {
                width: 32pt !important;
                text-align: right !important;
                padding: 2.5pt 8pt 2.5pt 4pt !important;
                color: #64748b !important;
                background-color: #020617 !important;
                border-right: 1px solid #1e293b !important;
                font-family: 'JetBrains Mono', monospace !important;
                font-size: 8.5pt !important;
                vertical-align: top !important;
            }

            .code-line-content {
                padding: 2.5pt 10pt !important;
                color: #6ee7b7 !important; /* Emerald-300 yang presisi */
                background-color: #0f172a !important;
                font-family: 'JetBrains Mono', 'Courier New', monospace !important;
                font-size: 9.5pt !important;
                white-space: pre !important;
                vertical-align: top !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100/70 text-slate-800 min-h-screen antialiased flex flex-col">

    @php
        $hasResults = isset($results) && !empty($results['questions']);
    @endphp

    <!-- Header Navbar Formal & Minimalis -->
    <header class="bg-white border-b border-slate-200/90 sticky top-0 z-30 shadow-xs no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3.5">
                <div class="w-9 h-9 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <svg class="w-5 h-5 text-slate-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <h1 class="text-base font-bold text-slate-900 tracking-tight">Generator Soal & Bank Asesmen</h1>
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            Moda Mandiri (Lokal)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">Penyusun instrumen evaluasi pembelajaran berbasis materi terpadu</p>
                </div>
            </div>

            <!-- Action Controls di Header -->
            <div class="flex items-center space-x-2.5">
                @if($hasResults)
                    <button type="button" onclick="openSidebar()" class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition">
                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        <span>Atur Materi & Parameter</span>
                    </button>
                @endif

                <div class="hidden sm:flex items-center px-2.5 py-1 rounded-md text-xs font-medium text-slate-600 bg-slate-50 border border-slate-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span>
                    Standar Anti-Klise Aktif
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">

        @if(session('status'))
            <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs sm:text-sm flex items-center justify-between shadow-xs no-print">
                <div class="flex items-center space-x-2.5">
                    <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 font-bold ml-2 text-base">&times;</button>
            </div>
        @endif

        @if(isset($errors) && $errors->any())
            <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs sm:text-sm shadow-xs no-print">
                <div class="font-bold mb-1">Perhatian:</div>
                <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-800">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($hasResults)
            <!-- ========================================================================= -->
            <!-- TAMPILAN SETELAH GENERATE: HASIL SOAL FULL-WIDTH DENGAN SIDEBAR TERSEMBUNYI -->
            <!-- ========================================================================= -->
            <div class="space-y-6">

                <!-- Action Bar & Ringkasan Paket Soal -->
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4 no-print">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <h2 class="text-lg font-bold text-slate-900">Naskah Paket Soal Terverifikasi</h2>
                            <span class="px-2 py-0.5 text-[11px] font-semibold rounded bg-slate-100 text-slate-700 border border-slate-200">
                                {{ count($results['questions']) }} Butir Soal
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                            <span>Mesin: <strong class="text-slate-700 font-medium">{{ $results['engine_used'] }}</strong></span>
                            <span>•</span>
                            <span>Cakupan Materi: <strong class="text-slate-700 font-medium">{{ $results['material_stats']['target_percentage'] }}% ({{ $results['material_stats']['words_sampled'] }} kata)</strong></span>
                            <span>•</span>
                            <span>Tingkat Kognitif: <strong class="text-slate-700 font-medium">Bloom C2–C5 (HOTS)</strong></span>
                        </div>
                    </div>

                    <!-- Tombol Kelola & Ekspor -->
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" onclick="openSidebar()" class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Ubah Input Materi</span>
                        </button>

                        <!-- Ekspor Format XLSX (Excel Sesuai Template Bank Soal Sekolah) -->
                        <form action="{{ route('generator.export') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="questions_data" value="{{ json_encode($results['questions']) }}">
                            <input type="hidden" name="format" value="xlsx">
                            <button type="submit" class="px-3.5 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-xs transition flex items-center space-x-1.5" title="Ekspor berkas spreadsheet Microsoft Excel (.xlsx) sesuai template resmi bank soal sekolah">
                                <svg class="w-3.5 h-3.5 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Simpan XLSX (Excel)</span>
                            </button>
                        </form>

                        <!-- Pilihan Ekspor CSV Tambahan -->
                        <form action="{{ route('generator.export') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="questions_data" value="{{ json_encode($results['questions']) }}">
                            <input type="hidden" name="format" value="csv">
                            <button type="submit" class="px-2.5 py-2 rounded-lg bg-white hover:bg-slate-50 border border-slate-300 text-xs font-semibold text-slate-700 transition shadow-xs flex items-center space-x-1" title="Unduh format .CSV">
                                <span>.CSV</span>
                            </button>
                        </form>

                        <form action="{{ route('generator.export') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="questions_data" value="{{ json_encode($results['questions']) }}">
                            <input type="hidden" name="format" value="txt">
                            <button type="submit" class="px-3 py-2 rounded-lg bg-white hover:bg-slate-50 border border-slate-300 text-xs font-semibold text-slate-700 transition shadow-xs flex items-center space-x-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span>Unduh (.TXT)</span>
                            </button>
                        </form>

                        <button onclick="copyAllQuestions()" class="px-3 py-2 rounded-lg bg-white hover:bg-slate-50 border border-slate-300 text-xs font-semibold text-slate-700 transition shadow-xs flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            <span id="copyBtnText">Salin Semua</span>
                        </button>

                        <button onclick="window.print()" class="px-3 py-2 rounded-lg bg-white hover:bg-slate-50 border border-slate-300 text-xs font-semibold text-slate-700 transition shadow-xs flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H9v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span>Cetak / Simpan PDF</span>
                        </button>
                    </div>
                </div>

                <!-- Daftar Naskah Soal -->
                <div class="space-y-4 max-w-5xl mx-auto">
                    @foreach($results['questions'] as $q)
                        <div class="question-card bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
                            <!-- Header Item Soal -->
                            <div class="question-header flex items-center justify-between pb-3 border-b border-slate-100">
                                <div class="flex items-center space-x-2.5">
                                    <span class="w-7 h-7 rounded-md bg-slate-900 text-white font-bold text-xs flex items-center justify-center print:hidden">
                                        {{ $q['number'] }}
                                    </span>
                                    <span class="question-number hidden print:inline">
                                        {{ $q['number'] }}.
                                    </span>
                                    <span class="question-badge px-2 py-0.5 rounded text-[11px] font-semibold border {{ $q['type'] === 'pilihan_ganda' ? 'bg-slate-50 text-slate-700 border-slate-200' : 'bg-amber-50 text-amber-800 border-amber-200' }}">
                                        {{ $q['type'] === 'pilihan_ganda' ? 'PILIHAN GANDA' : 'ESAI ANALITIS' }}
                                    </span>
                                    <span class="text-xs text-slate-500 font-medium print:text-[10pt] print:text-slate-600">
                                        ({{ $q['bloom_level'] ?? 'Tingkat Kognitif HOTS' }})
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-400 font-medium no-print">
                                    Standar Bebas Klise
                                </span>
                            </div>

                            <!-- Batang Soal (Pertanyaan) -->
                            <div class="question-stem text-base text-slate-900 font-medium leading-relaxed">
                                {{ $q['question'] }}
                            </div>

                            <!-- Tabel Potongan Kode Bernomor Baris (Jika Ada Kodingan) -->
                            @if(!empty($q['code_snippet']))
                                <div class="code-table-container my-3 rounded-lg border border-slate-800 bg-slate-900 text-slate-100 overflow-hidden shadow-xs">
                                    <div class="code-table-header px-3.5 py-1.5 bg-slate-950/80 border-b border-slate-800 flex items-center justify-between text-xs">
                                        <div class="flex items-center space-x-2">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-800 text-slate-300">
                                                {{ strtoupper($q['code_lang'] ?? 'KODE PROGRAM') }}
                                            </span>
                                            <span class="text-[11px] text-slate-400 font-mono">Tabel Baris Kode Program</span>
                                        </div>
                                        <button type="button" onclick="copySnippet(this)" class="no-print px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-[11px] font-mono transition">
                                            Salin Kode
                                        </button>
                                    </div>
                                    <div class="overflow-x-auto max-h-80 print:max-h-none print:overflow-visible">
                                        <table class="code-table w-full text-left border-collapse font-mono text-xs">
                                            <tbody>
                                                @foreach(explode("\n", rtrim($q['code_snippet'])) as $lineIndex => $line)
                                                    <tr class="code-row hover:bg-slate-800/40">
                                                        <td class="code-line-number w-12 py-1 px-3 text-right text-slate-500 select-none bg-slate-950/40 border-r border-slate-800 text-[11px]">
                                                            {{ $lineIndex + 1 }}
                                                        </td>
                                                        <td class="code-line-content py-1 px-4 text-emerald-300 whitespace-pre leading-relaxed select-text font-normal">{{ $line }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <!-- Pilihan Jawaban (Jika Pilihan Ganda) -->
                            @if(!empty($q['options']))
                                <div class="options-container grid grid-cols-1 gap-2 pt-1">
                                    @foreach($q['options'] as $opt)
                                        <div class="option-item p-3 rounded-lg border border-slate-200 text-xs sm:text-sm text-slate-700 bg-slate-50/40 hover:bg-white hover:border-slate-300 transition">
                                            {{ $opt }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Kunci & Pembahasan -->
                            <details class="group pt-2 border-t border-slate-100 no-print">
                                <summary class="cursor-pointer text-xs font-semibold text-slate-700 hover:text-slate-900 flex items-center justify-between select-none py-1">
                                    <span class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1.5 transform group-open:rotate-90 transition-transform text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        Kunci Jawaban & Panduan Pembahasan
                                    </span>
                                    <span class="text-[11px] text-slate-400 font-normal">Buka rincian</span>
                                </summary>
                                <div class="mt-2.5 p-3.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 space-y-2">
                                    <div>
                                        <span class="font-bold text-slate-900">Kunci / Jawaban Target:</span>
                                        <span class="font-semibold text-emerald-800 ml-1">{{ $q['correct_answer'] }}</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900">Pembahasan / Rubrik Penilaian:</span>
                                        <p class="mt-0.5 text-slate-600 leading-relaxed">{{ $q['explanation'] }}</p>
                                    </div>
                                    @if(!empty($q['source_snippet']))
                                        <div class="pt-2 text-[11px] text-slate-500 border-t border-slate-200/80">
                                            <span class="font-medium text-slate-700">Rujukan Kalimat Materi:</span>
                                            <p class="mt-0.5 text-slate-600 italic">"{{ $q['source_snippet'] }}"</p>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
            </div>

        @else
            <!-- ========================================================================= -->
            <!-- TAMPILAN AWAL (BELUM GENERATE): FORMULIR INPUT TERBUKA DI TENGAH -->
            <!-- ========================================================================= -->
            <div class="max-w-3xl mx-auto py-2">
                <div class="bg-white rounded-xl border border-slate-200 p-6 sm:p-8 shadow-xs space-y-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Unggah Materi & Parameter Soal</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Masukkan satu atau beberapa dokumen pembelajaran untuk dikonversi menjadi instrumen evaluasi terstandarisasi.</p>
                    </div>

                    @include('generator_form_fields')
                </div>
            </div>
        @endif

    </main>

    <!-- ========================================================================= -->
    <!-- SLIDE-OVER SIDEBAR: PANEL MATERI & PENGATURAN (MUNCUL KETIKA DIBUTUHKAN) -->
    <!-- ========================================================================= -->
    @if($hasResults)
        <!-- Backdrop -->
        <div id="sidebarBackdrop" onclick="closeSidebar()" class="fixed inset-0 bg-slate-900/40 z-40 backdrop-blur-xs transition-opacity duration-300 opacity-0 pointer-events-none no-print"></div>

        <!-- Sidebar Drawer Panel -->
        <aside id="sidebarPanel" class="fixed inset-y-0 left-0 z-50 w-full sm:w-[480px] bg-white shadow-xl border-r border-slate-200 flex flex-col transform -translate-x-full transition-transform duration-300 ease-in-out no-print">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/70">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Panel Materi & Parameter</h3>
                    <p class="text-xs text-slate-500">Sesuaikan berkas atau ubah konfigurasi soal</p>
                </div>
                <button type="button" onclick="closeSidebar()" class="w-8 h-8 rounded-lg hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center font-bold text-base transition">
                    &times;
                </button>
            </div>

            <!-- Sidebar Scrollable Body Form -->
            <div class="flex-1 overflow-y-auto p-5 space-y-5">
                @include('generator_form_fields')
            </div>
        </aside>
    @endif

    <!-- Footer Formal -->
    <footer class="mt-auto border-t border-slate-200 bg-white py-4 text-center text-xs text-slate-400 no-print">
        <p>Instrumen Asesmen Pembelajaran Terstandarisasi • 100% Eksekusi Komputasi Lokal</p>
    </footer>

    <!-- Shared JavaScript Logic -->
    <script>
        // Toggle Sidebar Functions
        function openSidebar() {
            const sidebar = document.getElementById('sidebarPanel');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar && backdrop) {
                backdrop.classList.remove('opacity-0', 'pointer-events-none');
                backdrop.classList.add('opacity-100', 'pointer-events-auto');
                sidebar.classList.remove('-translate-x-full');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebarPanel');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar && backdrop) {
                backdrop.classList.remove('opacity-100', 'pointer-events-auto');
                backdrop.classList.add('opacity-0', 'pointer-events-none');
                sidebar.classList.add('-translate-x-full');
            }
        }

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });

        // Contoh Teks Materi
        const sampleMaterialText = `Fotosintesis merupakan proses biokimia yang dilakukan oleh tumbuhan hijau, alga, dan beberapa jenis bakteri untuk memproduksi energi berupa glukosa dengan memanfaatkan energi cahaya matahari. Proses ini berlangsung terutama di dalam kloroplas, organel sel yang mengandung pigmen klorofil.

Fotosintesis terbagi menjadi dua tahapan utama, yaitu reaksi terang dan reaksi gelap (Siklus Calvin). Reaksi terang berlangsung di membran tilakoid dan membutuhkan keberadaan cahaya matahari secara langsung. Pada tahap ini, energi foton diserap oleh klorofil untuk memecah molekul air (fotolisis air), yang menghasilkan gas oksigen (O2) sebagai produk sampingan serta molekul berenergi tinggi yaitu ATP dan NADPH.

Sebaliknya, reaksi gelap terjadi di stroma kloroplas dan tidak memerlukan cahaya secara langsung, meskipun tetap bergantung pada produk ATP dan NADPH yang dihasilkan dari reaksi terang. Dalam Siklus Calvin, enzim RuBisCO memfiksasi karbon dioksida (CO2) dari udara untuk diubah menjadi molekul gula berkarbon tiga (G3P), yang kemudian dirangkai menjadi glukosa.

Faktor-faktor pembatas yang memengaruhi laju fotosintesis meliputi intensitas cahaya, konsentrasi karbon dioksida di atmosfer, suhu lingkungan, serta ketersediaan air tanah. Jika suhu melampaui batas optimum, enzim fotosintetik seperti RuBisCO akan mengalami denaturasi sehingga efisiensi fotosintesis menurun drastis.`;

        function loadSampleText() {
            const textarea = document.getElementById('material');
            if (textarea) {
                textarea.value = sampleMaterialText;
                updateWordCount();
            }
        }

        function updateWordCount() {
            const textarea = document.getElementById('material');
            if (!textarea) return;
            const text = textarea.value.trim();
            const words = text ? text.split(/\s+/).filter(w => w.length > 0).length : 0;
            const counter = document.getElementById('wordCounter');
            if (counter) counter.innerText = words + ' kata';
        }

        const materialEl = document.getElementById('material');
        if (materialEl) {
            materialEl.addEventListener('input', updateWordCount);
            updateWordCount();
        }

        // =========================================================================
        // PENGELOLA BANYAK BERKAS MATERI TANPA BATAS (MULTI-FILE ACCUMULATOR)
        // =========================================================================
        let accumulatedFiles = [];

        const filePicker = document.getElementById('material_file_picker');
        const hiddenInput = document.getElementById('material_files');
        const filesBox = document.getElementById('selectedFilesBox');
        const filesContainer = document.getElementById('filesContainer');
        const filesCountBadge = document.getElementById('filesCountBadge');
        const totalSizeLabel = document.getElementById('totalSizeLabel');

        function handleNewFiles(newFiles) {
            Array.from(newFiles).forEach(file => {
                const exists = accumulatedFiles.some(f => f.name === file.name && f.size === file.size);
                if (!exists) {
                    accumulatedFiles.push(file);
                }
            });
            syncFilesToInput();
            renderFileList();
        }

        function syncFilesToInput() {
            if (!hiddenInput) return;
            const dt = new DataTransfer();
            accumulatedFiles.forEach(f => dt.items.add(f));
            hiddenInput.files = dt.files;
        }

        function removeFile(index) {
            accumulatedFiles.splice(index, 1);
            syncFilesToInput();
            renderFileList();
        }

        function clearAllFiles() {
            accumulatedFiles = [];
            syncFilesToInput();
            renderFileList();
        }

        function renderFileList() {
            if (!filesBox || !filesContainer) return;

            if (accumulatedFiles.length === 0) {
                filesBox.classList.add('hidden');
                return;
            }

            filesBox.classList.remove('hidden');
            if (filesCountBadge) filesCountBadge.innerText = accumulatedFiles.length + ' berkas';
            filesContainer.innerHTML = '';

            let totalBytes = 0;
            accumulatedFiles.forEach((f, idx) => {
                totalBytes += f.size;
                const ext = f.name.split('.').pop().toUpperCase();
                const sizeKb = (f.size / 1024).toFixed(1);

                filesContainer.innerHTML += `
                    <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200 text-xs shadow-2xs">
                        <div class="flex items-center space-x-2 truncate max-w-[75%]">
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 font-mono">${ext}</span>
                            <span class="font-medium text-slate-800 truncate" title="${f.name}">${f.name}</span>
                        </div>
                        <div class="flex items-center space-x-2 flex-shrink-0">
                            <span class="text-slate-400 text-[11px] font-mono">${sizeKb} KB</span>
                            <button type="button" onclick="removeFile(${idx})" class="w-5 h-5 rounded hover:bg-slate-100 text-slate-400 hover:text-rose-600 flex items-center justify-center font-bold text-sm" title="Hapus berkas ini">
                                &times;
                            </button>
                        </div>
                    </div>
                `;
            });

            const totalKb = (totalBytes / 1024).toFixed(1);
            const totalMb = (totalBytes / (1024 * 1024)).toFixed(2);
            if (totalSizeLabel) {
                totalSizeLabel.innerText = totalBytes > 1048576 ? `Total: ${totalMb} MB` : `Total: ${totalKb} KB`;
            }
        }

        if (filePicker) {
            filePicker.addEventListener('change', function(e) {
                if (e.target.files && e.target.files.length > 0) {
                    handleNewFiles(e.target.files);
                    filePicker.value = '';
                }
            });
        }

        // Drag & Drop
        const dropArea = document.getElementById('dropArea');
        if (dropArea) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropArea.classList.add('border-slate-500', 'bg-slate-50');
                });
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('border-slate-500', 'bg-slate-50');
                });
            });
            dropArea.addEventListener('drop', (e) => {
                if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                    handleNewFiles(e.dataTransfer.files);
                }
            });
        }

        // Tambah Bab/Sesi Teks Tambahan secara Dinamis
        let textBlockCounter = 1;
        function addNewTextBlock() {
            textBlockCounter++;
            const container = document.getElementById('additionalTextBlocks');
            if (!container) return;
            const blockId = 'textBlock_' + textBlockCounter;

            const newBlock = document.createElement('div');
            newBlock.id = blockId;
            newBlock.className = 'p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-2';
            newBlock.innerHTML = `
                <div class="flex items-center justify-between text-[11px] text-slate-700 font-semibold">
                    <span>Bagian Teks #${textBlockCounter}</span>
                    <button type="button" onclick="document.getElementById('${blockId}').remove()" class="text-rose-600 hover:text-rose-800 font-medium text-xs">
                        &times; Hapus Bagian
                    </button>
                </div>
                <textarea name="materials[]" rows="3"
                    placeholder="Masukkan materi tambahan bagian #${textBlockCounter} di sini..."
                    class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:ring-1 focus:ring-slate-900 focus:border-slate-900 bg-white placeholder-slate-400 resize-y"></textarea>
            `;
            container.appendChild(newBlock);
        }

        // Salin Potongan Kode Tertentu dari Tabel
        function copySnippet(btn) {
            const table = btn.closest('.rounded-lg').querySelector('table');
            const rows = table.querySelectorAll('tr');
            let code = '';
            rows.forEach(r => {
                const td = r.querySelectorAll('td')[1];
                if (td) code += td.innerText + '\n';
            });
            navigator.clipboard.writeText(code.trim()).then(() => {
                const originalText = btn.innerText;
                btn.innerText = 'Tersalin!';
                setTimeout(() => btn.innerText = originalText, 2000);
            });
        }

        // Salin Seluruh Naskah Soal ke Clipboard
        function copyAllQuestions() {
            @if($hasResults)
                const questions = @json($results['questions']);
                let text = "=== NASKAH PAKET SOAL ASESMEN PEMBELAJARAN ===\n\n";
                questions.forEach(q => {
                    text += `${q.number}. (${q.type.toUpperCase()})\n${q.question}\n`;
                    if (q.code_snippet) {
                        text += `\n[TABEL KODE PROGRAM (${(q.code_lang || 'CODE').toUpperCase()})]:\n`;
                        const lines = q.code_snippet.split('\n');
                        lines.forEach((l, idx) => {
                            text += `  ${idx + 1} | ${l}\n`;
                        });
                        text += `\n`;
                    }
                    if (q.options && q.options.length > 0) {
                        q.options.forEach(opt => text += `   ${opt}\n`);
                    }
                    text += `\nKUNCI: ${q.correct_answer}\nPEMBAHASAN: ${q.explanation}\n\n-------------------------\n\n`;
                });

                navigator.clipboard.writeText(text).then(() => {
                    const btn = document.getElementById('copyBtnText');
                    if (btn) {
                        btn.innerText = 'Tersalin!';
                        setTimeout(() => btn.innerText = 'Salin Semua', 2000);
                    }
                });
            @endif
        }
    </script>
</body>
</html>
