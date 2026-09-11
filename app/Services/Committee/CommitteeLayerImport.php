<?php

namespace App\Services\Committee;

use App\Models\CommitteeAssignment;
use App\Models\KpnEmployee;
use App\Support\SimpleXlsx;

/**
 * Membaca file template committee (employee_id, approver_id, layer) menjadi
 * daftar "layer -> approver" yang siap mengisi form Add/Edit Committee.
 *
 * Kolom employee_id sengaja TIDAK dipakai: satu baris committee_assignments
 * berlaku untuk satu kombinasi (approval type + BU + Unit + range budget),
 * bukan per karyawan. BU & Unit diambil dari pilihan pada form.
 *
 * Hasilnya hanya mengisi form — penyimpanan tetap lewat tombol Save, agar
 * kesalahan isi file bisa dilihat dan dibetulkan dulu.
 */
class CommitteeLayerImport
{
    /** Kolom bawaan bila file tidak punya baris header. */
    private const KOLOM_DEFAULT = ['employee_id' => 0, 'approver_id' => 1, 'layer' => 2];

    /**
     * @return array{rows: array<int, array{layer:int, approver_id:string, email:string, label:string}>, notes: array<int, string>}
     *
     * @throws \RuntimeException bila file tidak terbaca atau tidak ada baris yang bisa dipakai
     */
    public function parse(string $path, string $extension, string $approvalType, int $maxLayers): array
    {
        $baris = strtolower($extension) === 'xlsx'
            ? SimpleXlsx::read($path)
            : $this->bacaCsv($path);

        $baris = array_values(array_filter(
            $baris,
            fn ($r) => collect($r)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()
        ));

        if ($baris === []) {
            throw new \RuntimeException('The file has no content.');
        }

        [$kolom, $mulai] = $this->petakanKolom($baris);

        $notes   = [];   // [nomor baris, pesan] — diurutkan belakangan agar sesuai urutan file
        $mentah  = [];   // layer => ['approver_id' => ..., 'baris' => nomor baris di file]
        $sponsor = CommitteeAssignment::usesSponsorLayer($approvalType);

        foreach (array_slice($baris, $mulai) as $i => $r) {
            $nomor    = $mulai + $i + 1;
            $approver = $this->sel($r, $kolom['approver_id'] ?? null);
            $layerRaw = $this->sel($r, $kolom['layer'] ?? null);
            $layer    = (int) preg_replace('/\D/', '', $layerRaw);

            if ($layerRaw === '' || $layer < 1 || $layer > $maxLayers) {
                $notes[] = [$nomor, "Row {$nomor}: layer \"{$layerRaw}\" is not between 1 and {$maxLayers} — skipped."];
                continue;
            }

            if ($approver === '') {
                $notes[] = [$nomor, "Row {$nomor}: approver_id is empty — skipped."];
                continue;
            }

            if ($sponsor && $layer === CommitteeAssignment::SPONSOR_LAYER) {
                $notes[] = [$nomor, "Row {$nomor}: layer 1 is reserved for the Project Sponsor — skipped."];
                continue;
            }

            if (isset($mentah[$layer])) {
                $notes[] = [$nomor, "Row {$nomor}: layer {$layer} already filled by row {$mentah[$layer]['baris']} — skipped."];
                continue;
            }

            $mentah[$layer] = ['approver_id' => $approver, 'baris' => $nomor];
        }

        if ($mentah === []) {
            throw new \RuntimeException(
                'The file does not match the template. It needs the columns employee_id, approver_id and layer.'
            );
        }

        $employees = $this->cariEmployee(array_column($mentah, 'approver_id'));

        $rows = [];
        foreach ($mentah as $layer => $d) {
            $emp = $employees[$this->kunci($d['approver_id'])] ?? null;

            if (! $emp) {
                $notes[] = [$d['baris'], "Row {$d['baris']}: employee ID {$d['approver_id']} was not found — skipped."];
                continue;
            }

            if (trim((string) $emp->email) === '') {
                $notes[] = [$d['baris'], "Row {$d['baris']}: {$emp->fullname} has no email address, so they cannot be an approver — skipped."];
                continue;
            }

            $rows[] = [
                'layer'       => $layer,
                'approver_id' => $d['approver_id'],
                'email'       => $emp->email,
                'label'       => $emp->label(),
            ];
        }

        usort($rows, fn ($a, $b) => $a['layer'] <=> $b['layer']);
        usort($notes, fn ($a, $b) => $a[0] <=> $b[0]);
        $notes = array_column($notes, 1);

        if ($rows === []) {
            throw new \RuntimeException('No rows could be used. ' . implode(' ', $notes));
        }

        return ['rows' => $rows, 'notes' => $notes];
    }

    /**
     * Tentukan indeks kolom dari baris header. Bila tidak ada header yang dikenali,
     * pakai urutan bawaan template (A=employee_id, B=approver_id, C=layer).
     *
     * @return array{0: array<string,int>, 1: int}  [peta kolom, indeks baris data pertama]
     */
    private function petakanKolom(array $baris): array
    {
        $header = array_map(
            fn ($v) => preg_replace('/[^a-z_]/', '', str_replace(' ', '_', strtolower(trim((string) $v)))),
            $baris[0]
        );

        $peta = [];
        foreach ($header as $i => $nama) {
            if (in_array($nama, ['layer', 'approver_id', 'employee_id'], true)) {
                $peta[$nama] = $i;
            }
        }

        // Header dianggap ada hanya bila kolom penting benar-benar dikenali.
        return isset($peta['layer'], $peta['approver_id'])
            ? [$peta, 1]
            : [self::KOLOM_DEFAULT, 0];
    }

    private function sel(array $baris, ?int $i): string
    {
        return $i === null ? '' : trim((string) ($baris[$i] ?? ''));
    }

    /**
     * Ambil employee untuk sekumpulan employee_id dalam satu query.
     *
     * Excel membuang angka nol di depan bila sel tersimpan sebagai angka
     * ("01123070004" menjadi 1123070004), jadi tiap ID juga dicari dalam bentuk
     * yang sudah dikembalikan nol depannya.
     *
     * @return array<string, KpnEmployee>  terindeks ID tanpa nol depan
     */
    private function cariEmployee(array $ids): array
    {
        $kandidat = [];

        foreach (array_unique($ids) as $id) {
            $kandidat[] = $id;

            if (ctype_digit($id)) {
                for ($len = strlen($id) + 1; $len <= 13; $len++) {
                    $kandidat[] = str_pad($id, $len, '0', STR_PAD_LEFT);
                }
            }
        }

        if ($kandidat === []) {
            return [];
        }

        return KpnEmployee::query()
            ->whereNull('deleted_at')
            ->whereIn('employee_id', array_values(array_unique($kandidat)))
            ->get()
            ->keyBy(fn ($e) => $this->kunci((string) $e->employee_id))
            ->all();
    }

    /** Kunci pencocokan: angka nol di depan diabaikan agar "1123..." = "01123...". */
    private function kunci(string $id): string
    {
        return ctype_digit($id) ? ltrim($id, '0') : strtoupper($id);
    }

    /** CSV/TSV: pemisah ditebak dari baris pertama, BOM Excel dibuang. */
    private function bacaCsv(string $path): array
    {
        $isi = (string) file_get_contents($path);
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);

        $pertama = strtok($isi, "\n") ?: '';
        $pemisah = substr_count($pertama, ';') > substr_count($pertama, ',') ? ';'
            : (substr_count($pertama, "\t") > substr_count($pertama, ',') ? "\t" : ',');

        $hasil = [];
        foreach (preg_split('/\r\n|\r|\n/', $isi) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $hasil[] = array_map('trim', str_getcsv($line, $pemisah));
        }

        return $hasil;
    }
}
