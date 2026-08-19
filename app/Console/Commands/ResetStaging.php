<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Reset data staging: mengosongkan SEMUA data transaksi + master/config sekaligus
 * dengan memanggil tms:clear-ideas-projects & tms:clear-config.
 * Struktur/flow/kode TIDAK diubah — hanya data.
 *
 * Contoh:
 *   php artisan tms:reset-staging            (dengan konfirmasi)
 *   php artisan tms:reset-staging --force     (tanpa konfirmasi)
 *   php artisan tms:reset-staging --force --files
 */
class ResetStaging extends Command
{
    protected $signature = 'tms:reset-staging
        {--force : Jalankan tanpa konfirmasi (non-interaktif)}
        {--files : Hapus juga file fisik (lampiran ide/project & guideline)}';

    protected $description = 'Reset data staging: kosongkan Ideas & Projects + master/config (categories, committee, SLA, guidelines). Flow tetap.';

    public function handle(): int
    {
        $this->warn('RESET DATA STAGING — akan mengosongkan:');
        $this->line('  • Transaksi: Ideas & Projects (+ tabel anak)');
        $this->line('  • Master/config: Project Categories, Committee Assignments, SLA Settings, Guidelines');
        $this->comment('Structure/flow/code are NOT changed — data only. Reconfigure via the menu afterwards.');

        if (! $this->option('force') && ! $this->confirm('Lanjutkan reset SEMUA data di atas? PERMANEN.')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        // Sudah dikonfirmasi di sini → sub-command dijalankan dengan --force.
        $opts = ['--force' => true];
        if ($this->option('files')) {
            $opts['--files'] = true;
        }

        $this->newLine();
        $this->info('› tms:clear-ideas-projects');
        $this->call('tms:clear-ideas-projects', $opts);

        $this->newLine();
        $this->info('› tms:clear-config');
        $this->call('tms:clear-config', $opts);

        $this->newLine();
        $this->info('DONE. Staging data has been reset. Please reconfigure Committee, Category, SLA, and Guideline via the menu.');

        return self::SUCCESS;
    }
}
