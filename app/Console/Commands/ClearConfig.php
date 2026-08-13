<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Kosongkan DATA master/config yang sempat di-set — TANPA mengubah flow/kode:
 *   project_categories, committee_assignments, sla_settings, guidelines.
 *
 * Aplikasi tetap berjalan; tinggal di-set ulang lewat menu masing-masing.
 * Catatan alur: setelah committee_assignments dikosongkan, idea/project belum
 * punya approver sampai di-set lagi di menu Committee Assignment.
 *
 * Contoh:
 *   php artisan tms:clear-config
 *   php artisan tms:clear-config --force
 *   php artisan tms:clear-config --force --files
 */
class ClearConfig extends Command
{
    protected $signature = 'tms:clear-config
        {--force : Jalankan tanpa konfirmasi (non-interaktif)}
        {--files : Hapus juga file fisik guideline di storage}';

    protected $description = 'Kosongkan data master/config (project categories, committee assignments, SLA settings, guidelines). Flow tetap.';

    private array $tables = [
        'guidelines',
        'project_categories',
        'committee_assignments',
        'sla_settings',
    ];

    public function handle(): int
    {
        $db = DB::connection('mysql');

        $this->warn('Akan MENGHAPUS PERMANEN data master/config berikut (DB: ' . $db->getDatabaseName() . '):');
        $total = 0;
        foreach ($this->tables as $t) {
            $c = $db->table($t)->count();
            $total += $c;
            $this->line('  - ' . str_pad($t, 24) . $c . ' baris');
        }
        $this->line('  TOTAL: ' . $total . ' baris');
        $this->comment('Struktur & flow TIDAK diubah — tinggal di-set ulang lewat menu masing-masing.');

        if (! $this->option('force') && ! $this->confirm('Lanjutkan? Tindakan ini PERMANEN.')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        // Kumpulkan path file guideline SEBELUM truncate (untuk --files).
        $filePaths = $this->option('files')
            ? $db->table('guidelines')->pluck('file_path')->filter()->values()->all()
            : [];

        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->tables as $t) {
            $db->table($t)->truncate();
            $this->line('cleared: ' . $t);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');

        if ($this->option('files') && $filePaths) {
            $deleted = 0;
            foreach ($filePaths as $path) {
                foreach (['public', config('filesystems.default')] as $disk) {
                    try {
                        if (Storage::disk($disk)->exists($path)) {
                            Storage::disk($disk)->delete($path);
                            $deleted++;
                            break;
                        }
                    } catch (\Throwable $e) {
                        // abaikan disk tak valid
                    }
                }
            }
            $this->line("file guideline dihapus: {$deleted}/" . count($filePaths));
        }

        $this->info('SELESAI. Data master/config sudah dikosongkan. Silakan set ulang via menu.');

        return self::SUCCESS;
    }
}
