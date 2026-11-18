<form action="{{ route('generator.generate') }}" method="POST" enctype="multipart/form-data" id="generatorForm" class="space-y-5">
    @csrf

    <!-- Area Unggah Berkas Materi (Multi-Berkas Akumulatif Tanpa Batasan) -->
    <div class="space-y-3">
        <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
            Berkas Dokumen Materi
        </label>

        <div class="rounded-xl border border-dashed border-slate-300 hover:border-slate-400 bg-slate-50/50 hover:bg-slate-50 p-5 text-center transition relative cursor-pointer" id="dropArea">
            <input type="file" id="material_file_picker" multiple
                accept=".pdf,.docx,.doc,.pptx,.ppt,.xlsx,.xls,.csv,.zip,.rar,.png,.jpg,.jpeg,.webp,.bmp,.txt,.md" 
                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
            
            <!-- Hidden real input yang menampung file akumulatif untuk submit form -->
            <input type="file" name="material_files[]" id="material_files" multiple class="hidden">
            
            <div class="space-y-1.5 pointer-events-none">
                <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-700 mx-auto flex items-center justify-center shadow-2xs">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                </div>
                <div class="text-xs font-semibold text-slate-800">
                    Klik untuk memilih berkas <span class="font-normal text-slate-500">atau tarik ke sini</span>
                </div>
                <div class="text-[11px] text-slate-400">
                    PDF, DOCX, PPTX, XLSX, CSV, RAR, ZIP, atau Gambar (OCR) • Dapat ditambah berkali-kali
                </div>
            </div>
        </div>

        <!-- Daftar Berkas Terpilih -->
        <div id="selectedFilesBox" class="hidden rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-slate-700">Berkas Terlampir:</span>
                <button type="button" onclick="clearAllFiles()" class="text-[11px] text-slate-500 hover:text-rose-600">
                    Hapus Semua
                </button>
            </div>
            <div id="filesContainer" class="space-y-1.5 max-h-48 overflow-y-auto pr-1"></div>
            <div class="pt-1.5 border-t border-slate-200/80 flex items-center justify-between text-[11px] text-slate-500">
                <span id="totalSizeLabel">Total: 0 KB</span>
                <label for="material_file_picker" class="cursor-pointer text-slate-700 hover:text-slate-900 font-semibold">
                    + Tambah Berkas Lain
                </label>
            </div>
        </div>
    </div>

    <!-- Rincian Sumber Materi yang Telah Diproses (Jika Ada) -->
    @if(isset($materialsSummary))
        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs space-y-1.5">
            <div class="flex items-center justify-between font-semibold text-slate-800">
                <span>{{ $materialsSummary['total_sources'] }} Sumber Dokumen Tergabung</span>
                <span class="font-mono text-slate-700">{{ number_format($materialsSummary['total_words_corpus']) }} kata</span>
            </div>
            <div class="max-h-24 overflow-y-auto space-y-1 pt-1 border-t border-slate-200 text-[11px] text-slate-600">
                @foreach($materialsSummary['sources'] as $src)
                    <div class="flex items-center justify-between py-0.5">
                        <span class="truncate max-w-[220px]" title="{{ $src['title'] }}">📄 {{ $src['title'] }}</span>
                        <span class="font-mono text-slate-500">{{ $src['words'] }} kata</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Input Teks Materi Langsung -->
    <div class="space-y-2.5">
        <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                Materi Teks / Bab Tambahan
            </label>
            <div class="flex items-center space-x-2">
                <button type="button" onclick="loadSampleText()" class="text-xs text-slate-600 hover:text-slate-900 font-medium hover:underline">
                    Isi Contoh
                </button>
                <span class="text-slate-300">•</span>
                <button type="button" onclick="addNewTextBlock()" class="text-xs text-slate-700 hover:text-slate-900 font-semibold flex items-center">
                    + Tambah Bagian
                </button>
            </div>
        </div>

        <div class="space-y-1">
            <div class="flex items-center justify-between text-[11px] text-slate-500">
                <span>Bagian Utama</span>
                <span id="wordCounter" class="font-mono">0 kata</span>
            </div>
            <textarea id="material" name="material" rows="4"
                placeholder="Salin atau ketik teks materi pembelajaran di sini..."
                class="w-full px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 focus:ring-1 focus:ring-slate-900 focus:border-slate-900 placeholder-slate-400 bg-white resize-y">{{ old('material', $inputs['material'] ?? '') }}</textarea>
        </div>

        <!-- Bagian Teks Tambahan Dinamis -->
        <div id="additionalTextBlocks" class="space-y-2.5"></div>
    </div>

    <!-- Parameter Porsi Materi (% Coverage) -->
    <div class="p-3.5 rounded-lg bg-slate-50 border border-slate-200 space-y-2.5">
        <div class="flex items-center justify-between">
            <label for="percentage" class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                Porsi Materi yang Diambil
            </label>
            <span id="percentBadge" class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-slate-900 text-white">
                {{ old('percentage', $inputs['percentage'] ?? 50) }}%
            </span>
        </div>

        <input type="range" id="percentage" name="percentage" min="10" max="100" step="5"
            value="{{ old('percentage', $inputs['percentage'] ?? 50) }}"
            oninput="document.getElementById('percentBadge').innerText = this.value + '%'"
            class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-slate-900">

        <div class="flex justify-between text-[11px] text-slate-500 font-medium">
            <span>10% (Ringkas)</span>
            <span>50% (Proporsional)</span>
            <span>100% (Penuh / Lengkap)</span>
        </div>
    </div>

    <!-- Konfigurasi Jumlah & Bentuk Soal -->
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="count" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                Jumlah Soal
            </label>
            <select id="count" name="count" class="w-full px-2.5 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 focus:ring-1 focus:ring-slate-900 focus:border-slate-900 bg-white font-medium">
                @foreach([3, 5, 10, 15, 20, 25, 30, 40, 50, 75, 100] as $c)
                    <option value="{{ $c }}" {{ old('count', $inputs['count'] ?? 5) == $c ? 'selected' : '' }}>
                        {{ $c }} Butir Soal
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="type" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                Bentuk Soal
            </label>
            <select id="type" name="type" class="w-full px-2.5 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 focus:ring-1 focus:ring-slate-900 focus:border-slate-900 bg-white font-medium">
                <option value="all" {{ old('type', $inputs['type'] ?? 'all') == 'all' ? 'selected' : '' }}>Campuran (PG & Esai)</option>
                <option value="multiple_choice" {{ old('type', $inputs['type'] ?? 'all') == 'multiple_choice' ? 'selected' : '' }}>Pilihan Ganda</option>
                <option value="essay" {{ old('type', $inputs['type'] ?? 'all') == 'essay' ? 'selected' : '' }}>Esai & Kasus</option>
            </select>
        </div>
    </div>

    <!-- Pilihan Mesin Pembuat Soal -->
    <div>
        <label for="model" class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1 flex items-center justify-between">
            <span>Mesin Generator Soal</span>
            <span class="text-[10px] text-slate-500 font-normal">Offline & Lokal</span>
        </label>
        <select id="model" name="model" class="w-full px-2.5 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 focus:ring-1 focus:ring-slate-900 focus:border-slate-900 bg-white font-medium">
            <optgroup label="Mesin Analitis Lokal (100% Standalone)">
                <option value="offline_engine" {{ old('model', $inputs['model'] ?? '') == 'offline_engine' ? 'selected' : '' }}>
                    Mesin Heuristik Semantik (Kausalitas & Konsep)
                </option>
                <option value="bloom_engine" {{ old('model', $inputs['model'] ?? '') == 'bloom_engine' ? 'selected' : '' }}>
                    Mesin Taksonomi Bloom (Evaluasi Kognitif C2–C5)
                </option>
                <option value="case_study_engine" {{ old('model', $inputs['model'] ?? '') == 'case_study_engine' ? 'selected' : '' }}>
                    Mesin Studi Kasus & Pemecahan Masalah
                </option>
                <option value="hybrid_ensemble" {{ old('model', $inputs['model'] ?? '') == 'hybrid_ensemble' ? 'selected' : '' }}>
                    Mesin Ensemble Terpadu (Kombinasi Bank Soal Besar)
                </option>
            </optgroup>

            <optgroup label="Server Model Bahasa Lokal">
                @if(!empty($localStatus['models']))
                    @foreach($localStatus['models'] as $m)
                        <option value="{{ $m }}" {{ old('model', $inputs['model'] ?? '') == $m ? 'selected' : '' }}>
                            Ollama Lokal: {{ $m }}
                        </option>
                    @endforeach
                @else
                    <option value="ollama_auto" disabled>
                        Ollama Lokal (Tidak Terdeteksi)
                    </option>
                @endif
                <option value="lm_studio" {{ old('model', $inputs['model'] ?? '') == 'lm_studio' ? 'selected' : '' }}>
                    LM Studio / Jan / vLLM (Port 1234)
                </option>
            </optgroup>
        </select>
    </div>

    <!-- Tombol Submit Formal -->
    <button type="submit" id="btnSubmit" class="w-full py-2.5 px-4 rounded-lg font-semibold text-sm text-white bg-slate-900 hover:bg-slate-800 transition shadow-xs flex items-center justify-center space-x-2">
        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span>Buat Instrumen Soal</span>
    </button>
</form>
