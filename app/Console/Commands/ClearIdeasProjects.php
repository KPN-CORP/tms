<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Kosongkan data transaksi Ideas & Projects (beserta tabel anaknya). DESTRUKTIF.
 *
 * Contoh:
 *   php artisan tms:clear-ideas-projects            (dengan konfirmasi)
 *   php artisan tms:clear-ideas-projects --force    (tanpa konfirmasi — utk staging/script)
 *   php artisan tms:clear-ideas-projects --force --files --logs
 *
 * TIDAK menyentuh master/config (project_categories, sla_settings, committee_assignments,
 * business_units, roles, users, guidelines, dst).
 */
class ClearIdeasProjects extends Command
{
    protected $signature = 'tms:clear-ideas-projects
        {--force : Jalankan tanpa konfirmasi (non-interaktif)}
        {--files : Hapus juga file lampiran fisik di storage}
        {--logs : Kosongkan juga tabel activity_logs}';

    protected $description = 'Kosongkan data Ideas & Projects (beserta tabel anaknya). DESTRUKTIF — tidak menghapus master data.';

    /** Urutan aman: anak dulu, induk terakhir. */
    private array $tables = [
        'idea_approvals', 'idea_attachments', 'ideas',
        'project_approvals', 'project_attachments', 'project_budgets', 'project_histories',
        'project_members', 'project_status_logs', 'project_updates',
        'implementation_indicators', 'implementation_plans', 'projects',
    ];

    public function handle(): int
    {
        $db = DB::connection('mysql');

        // Ringkas jumlah baris yang akan dihapus.
        $this->warn('Akan MENGHAPUS PERMANEN data berikut (DB: ' . $db->getDatabaseName() . '):');
        $total = 0;
        foreach ($this->tables as $t) {
            $c = $db->table($t)->count();
            $total += $c;
            $this->line('  - ' . str_pad($t, 26) . $c . ' baris');
        }
        if ($this->option('logs')) {
            $this->line('  - ' . str_pad('activity_logs', 26) . $db->table('activity_logs')->count() . ' baris');
        }
        $this->line('  TOTAL: ' . $total . ' baris');

        if (! $this->option('force') && ! $this->confirm('Lanjutkan? Tindakan ini PERMANEN.')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        // Kumpulkan path file lampiran SEBELUM truncate (untuk --files).
        $filePaths = [];
        if ($this->option('files')) {
            $filePaths = collect()
                ->merge($db->table('idea_attachments')->pluck('file_path'))
                ->merge($db->table('project_attachments')->pluck('file_path'))
                ->filter()
                ->values()
                ->all();
        }

        // Kosongkan tabel (TRUNCATE → id reset ke 1).
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->tables as $t) {
            $db->table($t)->truncate();
            $this->line('cleared: ' . $t);
        }
        if ($this->option('logs')) {
            $db->table('activity_logs')->truncate();
            $this->line('cleared: activity_logs');
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');

        // Hapus file fisik (best-effort: coba disk 'public' lalu default).
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
                        // abaikan disk yang tak valid
                    }
                }
            }
            $this->line("file fisik dihapus: {$deleted}/" . count($filePaths));
        }

        $this->info('SELESAI. Data Ideas & Projects sudah dikosongkan.');

        return self::SUCCESS;
    }
}
