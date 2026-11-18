<?php

namespace App\Services;

use ZipArchive;

class XlsxExportService
{
    /**
     * Membuat berkas XLSX murni sesuai template resmi bank soal sekolah
     *
     * Template Kolom:
     * 1. Soal/Pertanyaan
     * 2. Jenis ( 1=PG, 2=Essai)
     * 3. Jawaban A
     * 4. Jawaban B
     * 5. Jawaban C
     * 6. Jawaban D
     * 7. Jawaban E
     * 8. Kunci Jawaban (A/B/C/D/E)
     * 9. Bobot Essai
     *
     * @param array $questions
     * @return string Path ke file berkas XLSX temporer yang siap diunduh
     */
    public function generateBankSoalXlsx(array $questions): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bank_soal_') . '.xlsx';

        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Gagal membuat arsip XLSX.");
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>' .
            '<sheet name="Bank Soal" sheetId="1" r:id="rId1"/>' .
            '</sheets>' .
            '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 5. xl/styles.xml (Gaya Header & Font Rapi)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2">' .
            '<font><name val="Calibri"/><sz val="11"/><color theme="1"/></font>' .
            '<font><b/><name val="Calibri"/><sz val="11"/><color rgb="FFFFFFFF"/></font>' .
            '</fonts>' .
            '<fills count="3">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="gray125"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>' . // Dark Slate Header
            '</fills>' .
            '<borders count="2">' .
            '<border><left/><right/><top/><bottom/><diagonal/></border>' .
            '<border>' .
            '<left style="thin"><color rgb="FFCBD5E1"/></left>' .
            '<right style="thin"><color rgb="FFCBD5E1"/></right>' .
            '<top style="thin"><color rgb="FFCBD5E1"/></top>' .
            '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>' .
            '<diagonal/>' .
            '</border>' .
            '</borders>' .
            '<cellStyleXfs count="1">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' .
            '</cellStyleXfs>' .
            '<cellXfs count="3">' .
            '<!-- 0: Teks Isi Normal -->' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">' .
            '<alignment vertical="top" wrapText="1"/>' .
            '</xf>' .
            '<!-- 1: Header Baris 1 (Bold, Dark, Center) -->' .
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' .
            '<alignment horizontal="center" vertical="center" wrapText="1"/>' .
            '</xf>' .
            '<!-- 2: Nilai Center (Jenis, Kunci, Bobot) -->' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">' .
            '<alignment horizontal="center" vertical="top"/>' .
            '</xf>' .
            '</cellXfs>' .
            '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml (Konten Lembar Kerja & Baris Data)
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<cols>' .
            '<col min="1" max="1" width="55" customWidth="1"/>' . // Soal/Pertanyaan
            '<col min="2" max="2" width="24" customWidth="1"/>' . // Jenis ( 1=PG, 2=Essai)
            '<col min="3" max="3" width="28" customWidth="1"/>' . // Jawaban A
            '<col min="4" max="4" width="28" customWidth="1"/>' . // Jawaban B
            '<col min="5" max="5" width="28" customWidth="1"/>' . // Jawaban C
            '<col min="6" max="6" width="28" customWidth="1"/>' . // Jawaban D
            '<col min="7" max="7" width="28" customWidth="1"/>' . // Jawaban E
            '<col min="8" max="8" width="25" customWidth="1"/>' . // Kunci Jawaban (A/B/C/D/E)
            '<col min="9" max="9" width="16" customWidth="1"/>' . // Bobot Essai
            '</cols>' .
            '<sheetData>';

        // Baris 1: Header Template
        $sheetXml .= '<row r="1" ht="28" customHeight="1">' .
            '<c r="A1" t="inlineStr" s="1"><is><t>Soal/Pertanyaan</t></is></c>' .
            '<c r="B1" t="inlineStr" s="1"><is><t>Jenis ( 1=PG, 2=Essai)</t></is></c>' .
            '<c r="C1" t="inlineStr" s="1"><is><t>Jawaban A</t></is></c>' .
            '<c r="D1" t="inlineStr" s="1"><is><t>Jawaban B</t></is></c>' .
            '<c r="E1" t="inlineStr" s="1"><is><t>Jawaban C</t></is></c>' .
            '<c r="F1" t="inlineStr" s="1"><is><t>Jawaban D</t></is></c>' .
            '<c r="G1" t="inlineStr" s="1"><is><t>Jawaban E</t></is></c>' .
            '<c r="H1" t="inlineStr" s="1"><is><t>Kunci Jawaban (A/B/C/D/E)</t></is></c>' .
            '<c r="I1" t="inlineStr" s="1"><is><t>Bobot Essai</t></is></c>' .
            '</row>';

        $rowNum = 2;
        foreach ($questions as $q) {
            $isPG = ($q['type'] ?? '') === 'pilihan_ganda' || ($q['type'] ?? '') === 'multiple_choice';
            $jenis = $isPG ? 1 : 2;

            // Batang Soal (Sertakan potongan kodingan jika ada)
            $stem = $q['question'] ?? '';
            if (!empty($q['code_snippet'])) {
                $lang = strtoupper($q['code_lang'] ?? 'KODE');
                $stem .= "\n\n[KODE PROGRAM {$lang}]:\n" . $q['code_snippet'];
            }

            // Opsi Jawaban A s.d. E
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

            // Kunci Jawaban
            $kunci = '';
            if ($isPG) {
                $kunciRaw = trim($q['correct_answer'] ?? '');
                if (preg_match('/^([A-E])\b/i', $kunciRaw, $km)) {
                    $kunci = strtoupper($km[1]);
                } else {
                    $kunci = $kunciRaw;
                }
            }

            // Bobot Essai
            $bobot = $isPG ? '' : 20;

            // Render Baris Data
            $sheetXml .= "<row r=\"{$rowNum}\">";
            $sheetXml .= $this->buildCellXml("A{$rowNum}", $stem, 0);
            $sheetXml .= $this->buildNumberCellXml("B{$rowNum}", $jenis, 2);
            $sheetXml .= $this->buildCellXml("C{$rowNum}", $optMap['A'], 0);
            $sheetXml .= $this->buildCellXml("D{$rowNum}", $optMap['B'], 0);
            $sheetXml .= $this->buildCellXml("E{$rowNum}", $optMap['C'], 0);
            $sheetXml .= $this->buildCellXml("F{$rowNum}", $optMap['D'], 0);
            $sheetXml .= $this->buildCellXml("G{$rowNum}", $optMap['E'], 0);
            $sheetXml .= $this->buildCellXml("H{$rowNum}", $kunci, 2);
            if ($bobot !== '') {
                $sheetXml .= $this->buildNumberCellXml("I{$rowNum}", $bobot, 2);
            } else {
                $sheetXml .= "<c r=\"I{$rowNum}\" s=\"2\"/>";
            }
            $sheetXml .= '</row>';

            $rowNum++;
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();

        return $tempFile;
    }

    /**
     * Membentuk elemen cell string XML dengan sanitasi aman
     */
    protected function buildCellXml(string $cellRef, string $value, int $styleIndex): string
    {
        if ($value === '') {
            return "<c r=\"{$cellRef}\" s=\"{$styleIndex}\"/>";
        }

        $clean = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        return "<c r=\"{$cellRef}\" t=\"inlineStr\" s=\"{$styleIndex}\"><is><t xml:space=\"preserve\">{$clean}</t></is></c>";
    }

    /**
     * Membentuk elemen cell angka XML
     */
    protected function buildNumberCellXml(string $cellRef, int|float $value, int $styleIndex): string
    {
        return "<c r=\"{$cellRef}\" s=\"{$styleIndex}\"><v>{$value}</v></c>";
    }
}
