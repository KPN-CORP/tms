<?php

namespace App\Support;

/**
 * Pembaca & penulis XLSX seadanya — cukup untuk template import 3 kolom.
 *
 * Dibuat sendiri, bukan memakai PhpSpreadsheet, supaya tidak menambah dependensi
 * besar hanya untuk satu lembar kerja sederhana.
 *
 * Nilai SELALU dikembalikan sebagai string: employee_id seperti "01123070004"
 * akan kehilangan angka nol di depan bila sempat dianggap angka.
 */
class SimpleXlsx
{
    /**
     * Baca sheet pertama. Mengembalikan array of array string, terindeks kolom
     * 0..n (kolom yang kosong tetap terisi string kosong).
     *
     * @throws \RuntimeException bila file bukan xlsx yang bisa dibaca
     */
    public static function read(string $path): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('File cannot be opened. Make sure it is a .xlsx or .csv file.');
        }

        try {
            $shared = self::sharedStrings($zip);
            $xml    = $zip->getFromName(self::firstSheetPath($zip));

            if ($xml === false) {
                throw new \RuntimeException('No worksheet found inside the file.');
            }

            return self::rows($xml, $shared);
        } finally {
            $zip->close();
        }
    }

    /** Daftar teks pada xl/sharedStrings.xml (dirujuk sel bertipe t="s"). */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $doc  = self::parse($xml);
        $list = [];

        foreach ($doc->si ?? [] as $si) {
            // <si> bisa berisi satu <t>, atau beberapa <r><t> (teks berformat campuran).
            $teks = '';
            foreach ($si->t ?? [] as $t) {
                $teks .= (string) $t;
            }
            foreach ($si->r ?? [] as $r) {
                foreach ($r->t ?? [] as $t) {
                    $teks .= (string) $t;
                }
            }
            $list[] = $teks;
        }

        return $list;
    }

    /**
     * Path sheet pertama. Namanya tidak selalu "sheet1.xml", jadi ditelusuri
     * lewat workbook.xml -> relationship r:id.
     */
    private static function firstSheetPath(\ZipArchive $zip): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');

        if ($wb !== false) {
            $doc   = self::parse($wb);
            $sheet = $doc->sheets->sheet[0] ?? null;
            $rid   = $sheet
                ? (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '')
                : '';

            $rels = $rid !== '' ? $zip->getFromName('xl/_rels/workbook.xml.rels') : false;

            if ($rels !== false) {
                foreach (self::parse($rels)->Relationship ?? [] as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');

                        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** Ubah XML sheet menjadi array baris, menghormati referensi sel (A1, C3, ...). */
    private static function rows(string $xml, array $shared): array
    {
        $doc   = self::parse($xml);
        $hasil = [];

        foreach ($doc->sheetData->row ?? [] as $row) {
            $baris = [];

            foreach ($row->c ?? [] as $c) {
                $kolom = self::columnIndex((string) $c['r']);
                $tipe  = (string) $c['t'];

                if ($tipe === 'inlineStr') {
                    $nilai = '';
                    foreach ($c->is->t ?? [] as $t) {
                        $nilai .= (string) $t;
                    }
                    foreach ($c->is->r ?? [] as $r) {
                        foreach ($r->t ?? [] as $t) {
                            $nilai .= (string) $t;
                        }
                    }
                } elseif ($tipe === 's') {
                    $nilai = $shared[(int) $c->v] ?? '';
                } else {
                    $nilai = (string) ($c->v ?? '');
                }

                $baris[$kolom] = trim($nilai);
            }

            if ($baris === []) {
                $hasil[] = [];
                continue;
            }

            // Rapatkan lubang kolom agar indeks 0..n selalu ada.
            $lebar = max(array_keys($baris)) + 1;
            $rapi  = [];
            for ($i = 0; $i < $lebar; $i++) {
                $rapi[$i] = $baris[$i] ?? '';
            }

            $hasil[] = $rapi;
        }

        return $hasil;
    }

    /** "BC12" -> 54 (indeks kolom berbasis 0). */
    private static function columnIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/i', $ref, $m);
        $huruf = strtoupper($m[1] ?? 'A');
        $n     = 0;

        foreach (str_split($huruf) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    private static function parse(string $xml): \SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            throw new \RuntimeException('The file contents could not be read.');
        }

        return $doc;
    }

    /**
     * Tulis satu sheet sederhana menjadi biner xlsx.
     *
     * Semua sel ditulis sebagai inline string, jadi "01123070004" tetap utuh saat
     * dibuka di Excel dan tidak berubah menjadi 1123070004.
     *
     * @param  array<int, array<int, string>>  $rows  baris pertama = header
     */
    public static function write(array $rows): string
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols><col min="1" max="3" width="18" customWidth="1"/></cols><sheetData>';

        foreach (array_values($rows) as $i => $baris) {
            $sheet .= '<row r="' . ($i + 1) . '">';
            foreach (array_values($baris) as $j => $nilai) {
                $sheet .= '<c r="' . self::columnLetter($j) . ($i + 1) . '" t="inlineStr"><is><t>'
                    . htmlspecialchars((string) $nilai, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    . '</t></is></c>';
            }
            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';

        $isi = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Committee" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            // Excel menolak membuka workbook tanpa styles.xml ("unreadable content"),
            // meski isinya hanya satu style kosong.
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
                . '<borders count="1"><border/></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
                . '</styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        foreach ($isi as $nama => $data) {
            $zip->addFromString($nama, $data);
        }

        $zip->close();
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $biner;
    }

    /** 0 -> "A", 26 -> "AA". */
    private static function columnLetter(int $i): string
    {
        $s = '';
        $i++;

        while ($i > 0) {
            $sisa = ($i - 1) % 26;
            $s    = chr(65 + $sisa) . $s;
            $i    = intdiv($i - 1, 26);
        }

        return $s;
    }
}
